<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Topics\AccessService;
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
        private readonly ?AccessService $access = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile();
        $subjects = TopicService::subjectsFor($profile);
        $form = max(1, min(4, (int) $request->query('form', $profile !== null ? (string) $profile['form'] : '1')));
        if ($profile !== null && !in_array($form, $profile['forms'] ?? [(int) $profile['form']], true)) {
            $form = (int) $profile['form'];
        }
        $subject = $request->query('subject', $subjects[0]);
        if (!in_array($subject, $subjects, true)) {
            $subject = $subjects[0];
        }

        $tree = $this->topics->tree($form, $subject);
        $decide = $profile !== null ? $this->access?->forStudent((int) $this->context->userId()) : null;
        if ($decide !== null) {
            foreach ($tree as &$t) {
                $t['access'] = $decide((int) $t['id']);
                foreach ($t['children'] as &$c) {
                    $c['access'] = $decide((int) $c['id']);
                }
                unset($c);
            }
            unset($t);
        }

        return Response::html($this->view->render('topics/index', [
            'title' => 'Topics',
            'form' => $form,
            'subject' => $subject,
            'subjects' => $subjects,
            'names' => Subjects::NAMES,
            'tree' => $tree,
            'isTutor' => $profile === null,
            'statuses' => RequestService::STATUSES,
            'forms' => $profile === null ? [1, 2, 3, 4] : ($profile['forms'] ?? [(int) $profile['form']]),
            'revision' => $profile['revision'] ?? null,
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

    /** "My class has covered it" / "my class has not reached it" / undo. */
    public function report(Request $request): Response
    {
        $topic = $this->topicOr404($request);
        $profile = $this->profile();
        if ($profile === null || $this->access === null) {
            throw new HttpException(403, 'Only students report on their class.');
        }
        $status = $request->input('status');
        $error = $this->access->report((int) $this->context->userId(), $profile, (int) $topic['id'], $status);
        if ($error !== '') {
            $this->session->flash('error', $error);
        } else {
            $state = $this->access->check($this->context->userId(), 'student', $topic)['state'];
            $this->session->flash('success', match (true) {
                $status === 'not_yet' => 'Noted. This topic stays closed for you until your class reaches it.',
                $status === 'clear' => 'Your answer was removed.',
                $state === 'awaiting_check' => 'Thank you. Your school’s teacher has not reported this yet, so the tutor will check before it opens.',
                default => 'Thank you. The topic is open for you. The tutor will check with your school.',
            });
        }

        return Response::redirect('/topics/' . $topic['id']);
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function showPage(array $topic, array $errors, array $old, int $status = 200): Response
    {
        $id = (int) $topic['id'];
        $parent = $topic['parent_id'] !== null ? $this->topics->find((int) $topic['parent_id']) : null;
        $profile = $this->profile();
        $access = $this->access?->check($this->context->userId(), $this->context->role(), $topic) ?? ['open' => true, 'basis' => 'school', 'state' => 'school'];

        return Response::html($this->view->render('topics/show', [
            'title' => (string) $topic['title'],
            'topic' => $topic,
            'parent' => $parent,
            'children' => $this->topics->children($id),
            'lessons' => $this->topics->lessons($id),
            'books' => $this->topics->textbookRefs($id, $profile),
            'access' => $access,
            'states' => AccessService::STATES,
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
