<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Community\DiscussionService;
use SmartCircle\Application\Community\ProgressService;
use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Partners\GuestTeacherService;
use SmartCircle\Application\Reviews\LessonReviewService;
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
        private readonly ?GuestTeacherService $guests = null,
        private readonly ?LessonReviewService $reviews = null,
        private readonly ?DiscussionService $discussion = null,
        private readonly ?ProgressService $progress = null,
    ) {
    }

    private function isGuest(): bool
    {
        return $this->context->role() === 'partner';
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
                && in_array((int) $l['form'], $profile['forms'] ?? [(int) $profile['form']], true)
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
            'guestCredit' => $this->guests?->credit((int) $lesson['id']),
            'approval' => $this->reviews?->approval((int) $lesson['id']),
            'thread' => in_array($this->context->role(), ['tutor', 'student'], true) ? $this->discussion?->thread((int) $lesson['id'], (int) $this->context->userId(), (string) $this->context->role()) : null,
            'ticket' => $this->context->role() === 'student' ? ['saved' => $this->progress?->ticket((int) $lesson['id'], (int) $this->context->userId())] : null,
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
        $uid = (int) $this->context->userId();
        $lessons = array_values(array_filter($this->lessons->allForTutor(), fn (array $l): bool => $this->isGuest() ? (int) $l['created_by'] === $uid
            : ($l['subject'] !== null ? $this->context->canTeach((string) $l['subject']) : ($this->context->subjects() === null || (int) $l['created_by'] === $uid))));

        return Response::html($this->view->render('studio/lessons/index', [
            'title' => 'My lessons',
            'lessons' => $lessons,
            'guestLessons' => $this->guests?->guestLessons() ?? [],
            'isGuest' => $this->isGuest(),
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
        if ($this->isGuest() && $lesson['status'] === 'published') {
            throw new HttpException(403, 'This lesson is published. Ask the tutor if it needs a change.');
        }
        $input = [];
        foreach (['title', 'form', 'subject', 'topic_id', 'summary', ...array_keys(LessonService::REAL_LIFE_SECTIONS)] as $field) {
            $input[$field] = $request->input($field);
        }

        $errors = $this->lessons->updateDetails((int) $lesson['id'], $input);
        if ($errors !== []) {
            return $this->editForm(array_merge($lesson, $input), $errors, [], 422);
        }

        if ($request->input('action') === 'publish' && $this->isGuest()) {
            // Guest teachers do not publish: the tutor does, after watching it.
            $fresh = $this->lessons->find((int) $lesson['id']) ?? $lesson;
            $missing = $this->lessons->missingForPublish($fresh);
            if ($missing !== []) {
                return $this->editForm($fresh, [], $missing, 422);
            }
            $this->guests?->submit((int) $lesson['id'], (string) ($this->context->user()['full_name'] ?? 'A guest teacher'), (string) $fresh['title']);
            $this->session->flash('success', 'Sent to the tutor. You will be told when it is published.');
        } elseif ($request->input('action') === 'publish') {
            $missing = $this->lessons->publish((int) $lesson['id']);
            if ($missing !== []) {
                $fresh = $this->lessons->find((int) $lesson['id']) ?? $lesson;
                return $this->editForm($fresh, [], $missing, 422);
            }
            $this->guests?->published((int) $lesson['id'], (string) ($this->lessons->find((int) $lesson['id'])['title'] ?? ''));
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
        if ($this->isGuest() && $lesson['status'] === 'published') {
            throw new HttpException(403, 'This lesson is published. Ask the tutor if it should be removed.');
        }
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
            'isGuest' => $this->isGuest(),
            'guestCredit' => $this->isGuest() ? null : $this->guests?->credit((int) $lesson['id']),
            'review' => $this->isGuest() ? null : $this->reviews?->state((int) $lesson['id']),
            'reviewHistory' => $this->isGuest() ? [] : ($this->reviews?->history((int) $lesson['id']) ?? []),
            'tickets' => $this->isGuest() ? null : $this->progress?->summary((int) $lesson['id']),
            'errors' => $errors,
            'missing' => $missing,
            'subjects' => $this->context->subjects() === null ? LessonService::SUBJECTS
                : array_intersect_key(LessonService::SUBJECTS, array_flip($this->context->subjects())),
            'sections' => LessonService::REAL_LIFE_SECTIONS,
            'topicOptions' => array_values(array_filter($this->topics?->options() ?? [], fn (array $o): bool => $this->context->canTeach((string) $o['subject']))),
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
