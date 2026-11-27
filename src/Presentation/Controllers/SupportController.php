<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Messages\SupportService;
use SmartCircle\Application\Students\StudentOverviewService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\PhoneNumber;

/** The private room (students) and the tutor's view of every room. */
final class SupportController
{
    public function __construct(
        private readonly SupportService $support,
        private readonly StudentOverviewService $students,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function mine(Request $request): Response
    {
        return $this->studentPage('', null, 200, (int) $request->query('quote', '0'));
    }

    public function send(Request $request): Response
    {
        $body = $request->input('body');
        $user = $this->context->user() ?? [];
        $error = $this->support->openByStudent((int) $this->context->userId(), (string) ($user['full_name'] ?? ''), $body, ($request->input('quote_id') !== '' ? (int) $request->input('quote_id') : null));
        if ($error !== null) {
            return $this->studentPage($body, $error, 422);
        }

        return Response::redirect('/support#latest');
    }

    private function studentPage(string $draft, ?string $error = null, int $status = 200, int $quoteId = 0): Response
    {
        return Response::html($this->view->render('support/mine', [
            'title' => 'Private room',
            't' => $this->support->room((int) $this->context->userId()),
            'me' => (int) $this->context->userId(),
            'draft' => $draft,
            'error' => $error,
            'quoteId' => $quoteId,
        ]), $status);
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('studio/support/index', [
            'title' => 'Private rooms',
            'rooms' => $this->support->rooms(),
        ]));
    }

    public function show(Request $request): Response
    {
        return $this->tutorPage((int) $request->param('id'), '', null, 200, (int) $request->query('quote', '0'));
    }

    public function reply(Request $request): Response
    {
        $id = (int) $request->param('id');
        $thread = $this->support->forTutor($id, (int) $this->context->userId(), (string) $this->context->role());
        if ($thread === null) {
            throw new HttpException(404);
        }
        $r = $this->context->isAdmin()
            ? $this->support->invite((int) $this->context->userId(), (int) $thread['conversation']['student_id'], $request->input('body'), ($request->input('quote_id') !== '' ? (int) $request->input('quote_id') : null))
            : ['error' => $this->support->replyAsTeam($id, (int) $this->context->userId(), $request->input('body'), ($request->input('quote_id') !== '' ? (int) $request->input('quote_id') : null)), 'id' => $id];
        if ($r['error'] !== null) {
            return $this->tutorPage($id, $request->input('body'), $r['error'], 422);
        }

        return Response::redirect('/studio/support/' . $id . '#latest');
    }

    public function invite(Request $request): Response
    {
        $studentId = (int) $request->param('id');
        if ($this->students->one($studentId) === null) {
            throw new HttpException(404);
        }
        $r = $this->support->invite((int) $this->context->userId(), $studentId, $request->input('body'));
        if ($r['error'] !== null) {
            $this->session->flash('error', $r['error']);

            return Response::redirect('/studio/students/' . $studentId . '#private-room');
        }
        $this->session->flash('success', 'Invitation sent. Please let the guardian know (button below).');

        return Response::redirect('/studio/support/' . $r['id']);
    }

    public function guardianNotified(Request $request): Response
    {
        $this->support->guardianNotified((int) $request->param('id'));
        $this->session->flash('success', 'Noted: the guardian has been told.');

        return Response::redirect('/studio/support/' . (int) $request->param('id'));
    }

    public function addMember(Request $request): Response
    {
        $id = (int) $request->param('id');
        $ok = $this->support->addMember($id, (int) $request->input('user_id'));
        $this->session->flash(...($ok ? ['success', 'Team tutor added. The student has been told who joined.'] : ['error', 'Could not add that person.']));

        return Response::redirect('/studio/support/' . $id);
    }

    private function tutorPage(int $id, string $draft, ?string $error = null, int $status = 200, int $quoteId = 0): Response
    {
        $thread = $this->support->forTutor($id, (int) $this->context->userId(), (string) $this->context->role());
        if ($thread === null) {
            throw new HttpException(404);
        }
        $isAdmin = $this->context->isAdmin();
        $student = $isAdmin ? $this->students->one((int) $thread['conversation']['student_id']) : null;
        $guardianLink = null;
        if ($student !== null && ($g = PhoneNumber::normalize((string) ($student['guardian_phone'] ?? ''))) !== null) {
            $guardianLink = 'https://wa.me/' . ltrim($g, '+') . '?text='
                . rawurlencode(SupportService::guardianText((string) ($student['guardian_name'] ?? ''), (string) $student['full_name']));
        }

        return Response::html($this->view->render('studio/support/show', [
            'title' => 'Private room · ' . ($thread['conversation']['student_name'] ?? ''),
            't' => $thread,
            'student' => $student,
            'guardianLink' => $guardianLink,
            'me' => (int) $this->context->userId(),
            'draft' => $draft,
            'error' => $error,
            'isAdmin' => $isAdmin,
            'quoteId' => $quoteId,
            'team' => $isAdmin ? array_values(array_filter($this->support->teamTutors(),
                static fn (array $p): bool => !in_array((int) $p['id'], array_map(static fn (array $m): int => (int) $m['user_id'], $thread['members']), true))) : [],
        ]), $status);
    }
}
