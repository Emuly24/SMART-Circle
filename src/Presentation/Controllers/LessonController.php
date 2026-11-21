<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Topics\TopicService;
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
    ) {
    }

    /* ---------- Watching (students and tutor) ---------- */

    public function index(Request $request): Response
    {
        $isTutor = $this->context->role() === 'tutor';

        return Response::html($this->view->render('lessons/index', [
            'title' => 'Lessons',
            'lessons' => $this->lessons->publishedForForm(null),
            'subjects' => LessonService::SUBJECTS,
            'isTutor' => $isTutor,
        ]));
    }

    public function show(Request $request): Response
    {
        $lesson = $this->watchable($request);

        return Response::html($this->view->render('lessons/show', [
            'title' => (string) $lesson['title'],
            'lesson' => $lesson,
            'subjects' => LessonService::SUBJECTS,
            'sections' => LessonService::REAL_LIFE_SECTIONS,
            'isTutor' => $this->context->role() === 'tutor',
        ]));
    }

    public function file(Request $request): Response
    {
        $lesson = $this->watchable($request);
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
