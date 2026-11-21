<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Topics\RequestService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Students: browse the syllabus for their form and subjects, and ask for help on a topic. */
final class TopicController
{
    public function __construct(
        private readonly TopicService $topics,
        private readonly RequestService $requests,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile();
        $subjects = TopicService::subjectsFor($profile);
        $form = $profile !== null ? (int) $profile['form'] : max(1, min(4, (int) $request->query('form', '1')));
        $subject = $request->query('subject', $subjects[0]);
        if (!in_array($subject, $subjects, true)) {
            $subject = $subjects[0];
        }

        return Response::html($this->view->render('topics/index', [
            'title' => 'Topics',
            'form' => $form,
            'subject' => $subject,
            'subjects' => $subjects,
            'names' => Subjects::NAMES,
            'tree' => $this->topics->tree($form, $subject),
            'isTutor' => $profile === null,
            'statuses' => RequestService::STATUSES,
        ]));
    }

    public function show(Request $request): Response
    {
        return $this->showPage($this->topicOr404($request), [], []);
    }

    public function ask(Request $request): Response
    {
        $topic = $this->topicOr404($request);
        $profile = $this->profile();
        if ($profile === null) {
            throw new HttpException(403, 'Only students ask for topics.');
        }
        $input = [
            'struggle_type' => $request->input('struggle_type'),
            'details' => $request->input('details'),
            'covered' => $request->input('covered'),
            'exception_reason' => $request->input('exception_reason'),
        ];
        $result = $this->requests->request((int) $topic['id'], (int) $this->context->userId(), $profile, $input);
        if ($result['errors'] !== []) {
            return $this->showPage($topic, $result['errors'], $input, 422);
        }

        $this->session->flash('success', match ($result['outcome']) {
            'requested' => 'Request sent. Classmates stuck on the same topic can add their voice.',
            'joined' => 'Added your voice. The more students ask, the sooner it is taught.',
            'already' => 'You already asked for this topic.',
            default => 'Your answer was updated.',
        });

        return Response::redirect('/topics/' . $topic['id']);
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function showPage(array $topic, array $errors, array $old, int $status = 200): Response
    {
        $id = (int) $topic['id'];
        $parent = $topic['parent_id'] !== null ? $this->topics->find((int) $topic['parent_id']) : null;
        $profile = $this->profile();

        return Response::html($this->view->render('topics/show', [
            'title' => (string) $topic['title'],
            'topic' => $topic,
            'parent' => $parent,
            'children' => $this->topics->children($id),
            'lessons' => $this->topics->lessons($id),
            'books' => $this->topics->textbookRefs($id, $profile),
            'schoolCoverage' => $this->topics->schoolCoverage($profile, $id),
            'request' => $this->requests->forTopic($id, (int) $this->context->userId()),
            'isStudent' => $this->context->role() === 'student',
            'names' => Subjects::NAMES,
            'struggles' => RequestService::STRUGGLES,
            'statuses' => RequestService::STATUSES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /** @return array<string, mixed>|null the student's profile; null for the tutor */
    private function profile(): ?array
    {
        if ($this->context->role() === 'tutor') {
            return null;
        }

        return $this->topics->studentProfile((int) $this->context->userId())
            ?? throw new HttpException(403, 'Topics are for admitted students.');
    }

    /** @return array<string, mixed> */
    private function topicOr404(Request $request): array
    {
        $topic = $this->topics->find((int) $request->param('id'));
        if ($topic === null || (!(bool) $topic['is_active'] && $this->context->role() !== 'tutor')
            || !TopicService::mayBrowse($this->profile(), (int) $topic['form'], (string) $topic['subject'])) {
            throw new HttpException(404);
        }

        return $topic;
    }
}
