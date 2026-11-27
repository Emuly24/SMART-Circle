<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Messages\MessageService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Messages and announcements for every signed-in person. */
final class MessageController
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('messages/index', [
            'title' => 'Messages',
            'items' => $this->messages->inbox($this->uid()),
            'me' => $this->uid(),
            'role' => $this->role(),
        ]));
    }

    public function create(Request $request): Response
    {
        return $this->form([], ['to' => $request->query('to')]);
    }

    public function store(Request $request): Response
    {
        $in = ['to' => $request->input('to'), 'subject' => $request->input('subject'), 'body' => $request->input('body')];
        $result = $this->messages->start($this->uid(), $this->role(), (int) $in['to'], $in['subject'], $in['body']);
        if ($result['errors'] !== []) {
            return $this->form($result['errors'], $in, 422);
        }
        $this->session->flash('success', 'Message sent.');

        return Response::redirect('/messages/' . $result['id']);
    }

    public function announce(Request $request): Response
    {
        $in = ['audience' => $request->input('audience'), 'subject' => $request->input('subject'), 'body' => $request->input('body')];
        $result = $this->messages->announce($this->uid(), $in['audience'], $in['subject'], $in['body'], $this->context->subjects());
        if ($result['errors'] !== []) {
            return $this->form($result['errors'], $in + ['mode' => 'announce'], 422);
        }
        $this->session->flash('success', 'Announcement sent.');

        return Response::redirect('/messages/' . $result['id']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $thread = $this->messages->open($id, $this->uid(), $this->role());
        if ($thread === null) {
            throw new HttpException(404);
        }
        if ($thread['conversation']['kind'] === 'support') {
            return Response::redirect($this->role() === 'tutor' ? '/studio/support/' . $id : '/support');
        }

        return $this->thread($thread, '', null, 200, (int) $request->query('quote', '0'));
    }

    public function reply(Request $request): Response
    {
        $id = (int) $request->param('id');
        $body = $request->input('body');
        $error = $this->messages->reply($id, $this->uid(), $this->role(), $body, ($request->input('quote_id') !== '' ? (int) $request->input('quote_id') : null));
        if ($error !== null) {
            $thread = $this->messages->open($id, $this->uid(), $this->role());
            if ($thread === null) {
                throw new HttpException(404);
            }

            return $this->thread($thread, $body, $error, 422);
        }

        return Response::redirect('/messages/' . $id . '#latest');
    }

    /** @param array<string, mixed> $thread */
    private function thread(array $thread, string $draft, ?string $error = null, int $status = 200, int $quoteId = 0): Response
    {
        return Response::html($this->view->render('messages/show', [
            'title' => (string) $thread['conversation']['subject'],
            't' => $thread,
            'me' => $this->uid(),
            'role' => $this->role(),
            'draft' => $draft,
            'error' => $error,
            'action' => '/messages/' . $thread['conversation']['id'] . '/reply',
            'quoteId' => $quoteId,
        ]), $status);
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function form(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('messages/new', [
            'title' => 'New message',
            'recipients' => $this->messages->recipients($this->uid(), $this->role()),
            'audiences' => MessageService::audiencesFor($this->context->subjects()),
            'isTutor' => in_array($this->role(), ['tutor', 'team'], true),
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    private function uid(): int
    {
        return (int) $this->context->userId();
    }

    private function role(): string
    {
        return (string) $this->context->role();
    }
}
