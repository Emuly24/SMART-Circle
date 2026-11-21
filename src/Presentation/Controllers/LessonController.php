<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Support\Subjects;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Csrf;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/**
 * Lessons: the tutor's list/edit/publish pages, the watch page and the file stream.
 */
final class LessonController
{
    public function __construct(
        private readonly LessonService $lessons,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?TopicService $topics = null,
        private readonly ?AccessService $access = null,
    ) {
    }

    /* ---------- Watching (students and tutor) ---------- */

    public function index(Request $request): Response
    {
        $isTutor = $this->context->role() === 'tutor';
        $lessons = $this->lessons->publishedForForm(null);
        if (!$isTutor) {
            // Students see their own form and subjects; lessons on topics their school
            // has not taught yet are shown locked.
            $profile = $this->topics?->studentProfile((int) $this->context->userId());
            $decide = $this->access?->forStudent((int) $this->context->userId());
            $lessons = array_values(array_filter($lessons, static fn (array $l): bool => $profile !== null
                && (int) $l['form'] === (int) $profile['form']
                && in_array($l['subject'], Subjects::forTrack((string) $profile['track']), true)));
            foreach ($lessons as &$l) {
                $l['access'] = $decide !== null && $l['topic_id'] !== null ? $decide((int) $l['topic_id']) : ['open' => true, 'basis' => 'untagged', 'state' => 'school'];
            }
            unset($l);
        }

        return Response::html($this->view->render('lessons/index', [
            'title' => 'Lessons',
            'lessons' => $lessons,
            'states' => AccessService::STATES,
            'subjects' => LessonService::SUBJECTS,
            'isTutor' => $isTutor,
        ]));
    }

    public function show(Request $request): Response
    {
        $lesson = $this->watchable($request);
        $access = $this->accessFor($lesson);
        if (!$access['open']) {
            return Response::html($this->view->render('lessons/locked', [
                'title' => (string) $lesson['title'],
                'lesson' => $lesson,
                'access' => $access,
                'states' => AccessService::STATES,
                'subjects' => LessonService::SUBJECTS,
            ]), 403);
        }
        if ($this->context->role() === 'student') {
            $this->access?->recordView((int) $this->context->userId(), (int) $lesson['id'], (string) $access['basis']);
        }

        return Response::html($this->view->render('lessons/show', [
            'title' => (string) $lesson['title'],
            'lesson' => $lesson,
            'subjects' => LessonService::SUBJECTS,
            'sections' => LessonService::REAL_LIFE_SECTIONS,
            'isTutor' => $this->context->role() === 'tutor',
            'topic' => !empty($lesson['topic_id']) ? $this->topics?->find((int) $lesson['topic_id']) : null,
        ]));
    }

    public function file(Request $request): Response
    {
        $lesson = $this->watchable($request);
        if (!$this->accessFor($lesson)['open']) {
            throw new HttpException(403, 'This lesson opens when your school has taught its topic.');
        }
        $parts = $this->lessons->fileParts((int) $lesson['id']);
        if ($parts === []) {
            throw new HttpException(404, 'This lesson file is missing on the server.');
        }

        return Response::files($parts, 'application/octet-stream', (int) $lesson['size_bytes']);
    }

    /* ---------- Tutor: manage ---------- */

    public function manage(Request $request): Response
    {
        $lessons = $this->lessons->allForTutor();

        return Response::html($this->view->render('studio/lessons/index', [
            'title' => 'My lessons',
            'lessons' => $lessons,
            'subjects' => LessonService::SUBJECTS,
            'missing' => array_combine(
                array_map(static fn (array $l): int => (int) $l['id'], $lessons),
                array_map(fn (array $l): array => $this->lessons->missingForPublish($l), $lessons)
            ) ?: [],
        ]));
    }

    public function edit(Request $request): Response
    {
        $lesson = $this->lessonOr404($request);

        return $this->editForm($lesson, [], []);
    }

    public function update(Request $request): Response
    {
        $lesson = $this->lessonOr404($request);
        $input = [];
        foreach (['title', 'form', 'subject', 'topic_id', 'summary', ...array_keys(LessonService::REAL_LIFE_SECTIONS)] as $field) {
            $input[$field] = $request->input($field);
        }

        $errors = $this->lessons->updateDetails((int) $lesson['id'], $input);
        if ($errors !== []) {
            return $this->editForm(array_merge($lesson, $input), $errors, [], 422);
        }

        if ($request->input('action') === 'publish') {
            $missing = $this->lessons->publish((int) $lesson['id']);
            if ($missing !== []) {
                $fresh = $this->lessons->find((int) $lesson['id']) ?? $lesson;
                return $this->editForm($fresh, [], $missing, 422);
            }
            $this->session->flash('success', 'Published. Students can now watch this lesson.');
        } else {
            $this->session->flash('success', 'Lesson details saved.');
        }

        return Response::redirect('/studio/lessons/' . $lesson['id']);
    }

    public function unpublish(Request $request): Response
    {
        $lesson = $this->lessonOr404($request);
        $this->lessons->unpublish((int) $lesson['id']);
        $this->session->flash('info', 'Unpublished. Students can no longer see this lesson.');

        return Response::redirect('/studio/lessons/' . $lesson['id']);
    }

    public function delete(Request $request): Response
    {
        $lesson = $this->lessonOr404($request);
        $this->lessons->delete((int) $lesson['id']);
        $this->session->flash('info', 'Lesson deleted.');

        return Response::redirect('/studio/lessons');
    }

    /* ---------- Helpers ---------- */

    /** @param array<string, mixed> $lesson @param array<string, string> $errors @param list<string> $missing */
    private function editForm(array $lesson, array $errors, array $missing, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/lessons/edit', [
            'title' => 'Edit lesson',
            'lesson' => $lesson,
            'errors' => $errors,
            'missing' => $missing,
            'subjects' => LessonService::SUBJECTS,
            'sections' => LessonService::REAL_LIFE_SECTIONS,
            'topicOptions' => $this->topics?->options() ?? [],
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function lessonOr404(Request $request): array
    {
        $id = (int) $request->param('id');

        return $this->lessons->find($id) ?? throw new HttpException(404);
    }

    /**
     * The honesty rule for one lesson. Lessons published before topics existed have no topic and stay open.
     * @param array<string, mixed> $lesson
     * @return array{open: bool, basis: ?string, state: string}
     */
    private function accessFor(array $lesson): array
    {
        if ($this->access === null || $this->context->role() === 'tutor' || empty($lesson['topic_id'])) {
            return ['open' => true, 'basis' => 'untagged', 'state' => 'school'];
        }
        $topic = $this->topics?->find((int) $lesson['topic_id']);

        return $topic === null ? ['open' => true, 'basis' => 'untagged', 'state' => 'school']
            : $this->access->check($this->context->userId(), $this->context->role(), $topic);
    }

    /** @return array<string, mixed> */
    private function watchable(Request $request): array
    {
        $lesson = $this->lessonOr404($request);
        if (!$this->lessons->canWatch($lesson, $this->context->role())) {
            // Hide unpublished lessons entirely rather than revealing they exist.
            throw new HttpException(404);
        }

        return $lesson;
    }
}
