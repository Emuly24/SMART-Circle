<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Jobs\JobRunner;
use SmartCircle\Application\Mail\MailService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** The admin's view of outgoing email: the queue, "Send now", a test email, retrying failures. */
final class MailController
{
    public function __construct(
        private readonly MailService $mail,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?JobRunner $jobs = null,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('studio/mail', ['title' => 'Email', 'digest' => $this->jobs?->job('digest')] + $this->mail->status()));
    }

    public function sendNow(Request $request): Response
    {
        $r = $this->mail->sendDue(0, 20.0);
        $msg = 'Sent ' . $r['sent'] . ($r['failed'] > 0 ? ', ' . $r['failed'] . ' failed (will retry): ' . $r['error'] : '') . '. ' . $r['left'] . ' still waiting.';
        $this->session->flash($r['failed'] > 0 || $r['error'] !== '' ? 'error' : 'success', $r['error'] !== '' && $r['sent'] === 0 && $r['failed'] === 0 ? $r['error'] : $msg);

        return Response::redirect('/studio/mail');
    }

    public function test(Request $request): Response
    {
        $to = trim($request->input('to'));
        $ok = $this->mail->queue($to, '', 'SMART Circle test email', "Hello,\n\nThis is a test email from your SMART Circle website. If you can read it, email is working.", 'test', null, null, (int) $this->context->userId());
        if (!$ok) {
            $this->session->flash('error', 'That email address does not look right.');
            return Response::redirect('/studio/mail');
        }

        return $this->sendNow($request);
    }

    public function retry(Request $request): Response
    {
        $n = $this->mail->retryFailed();
        $this->session->flash('info', $n . ' failed email' . ($n === 1 ? '' : 's') . ' will be tried again.');

        return Response::redirect('/studio/mail');
    }
}
