<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Reviews\NoteReviewService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Partner teachers check notes and sign; the tutor asks for checks; approvals show a signature. */
final class ReviewController
{
    public function __construct(
        private readonly NoteReviewService $reviews,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Partner teachers ---------- */

    public function queue(Request $request): Response
    {
        $uid = (int) $this->context->userId();

        return Response::html($this->view->render('partner/reviews', [
            'title' => 'Check notes',
            'me' => $this->reviews->reviewer($uid),
            'names' => Subjects::NAMES,
        ] + $this->reviews->queue($uid)));
    }

    public function show(Request $request): Response
    {
        return $this->page((int) $request->param('id'), [], []);
    }

    public function decide(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->reviews->forReviewer((int) $this->context->userId(), $id) === null) {
            throw new HttpException(404);
        }
        $r = $this->reviews->decide((int) $this->context->userId(), $id, $request->input('decision'), $request->input('comment'));
        if ($r['errors'] !== []) {
            return $this->page($id, $r['errors'], ['decision' => $request->input('decision'), 'comment' => $request->input('comment')], 422);
        }
        $this->session->flash('success', $r['decision'] === 'approved'
            ? 'Thank you. Your approval and signature now appear on these notes.'
            : 'Thank you. The tutor has your suggestions.');

        return Response::redirect('/partner/reviews');
    }

    public function image(Request $request): Response
    {
        $path = $this->reviews->reviewImage((int) $this->context->userId(), (int) $request->param('id'), (int) $request->param('block'))
            ?? throw new HttpException(404);

        return NoteController::imageResponse($path);
    }

    public function signatureForm(Request $request): Response
    {
        return $this->signaturePage('', $request->query('back'));
    }

    public function saveSignature(Request $request): Response
    {
        $error = $this->reviews->saveSignature((int) $this->context->userId(), $request->input('drawn'), $request->file('photo'), $request->input('consent') === '1');
        if ($error !== '') {
            return $this->signaturePage($error, $request->input('back'), 422);
        }
        $this->session->flash('success', 'Signature saved. You can now approve notes.');
        $back = $request->input('back');

        return Response::redirect(preg_match('#^/partner/reviews(/\d+)?$#', $back) ? $back : '/partner/reviews');
    }

    public function mySignature(Request $request): Response
    {
        $path = $this->reviews->signaturePath((int) $this->context->userId()) ?? throw new HttpException(404);

        return Response::files([$path], 'image/png', (int) filesize($path))->withHeader('Cache-Control', 'private, no-store');
    }

    /* ---------- The tutor asks ---------- */

    public function request(Request $request): Response
    {
        $id = (int) $request->param('id');
        $r = $this->reviews->requestReview($id);
        $this->session->flash(...($r['error'] !== '' ? ['info', $r['error']]
            : ['success', 'Sent to ' . $r['sent'] . ' partner teacher' . ($r['sent'] === 1 ? '' : 's') . ' to check.']));

        return Response::redirect('/studio/notes/' . $id . '#reviews');
    }

    /* ---------- Everyone: the signature on an approved note ---------- */

    public function signature(Request $request): Response
    {
        $s = $this->reviews->approvalSignature((int) $request->param('id')) ?? throw new HttpException(404);

        return Response::files([$s['path']], 'image/png', (int) filesize($s['path']))->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function page(int $id, array $errors, array $old, int $status = 200): Response
    {
        $r = $this->reviews->forReviewer((int) $this->context->userId(), $id) ?? throw new HttpException(404);

        return Response::html($this->view->render('partner/review', [
            'title' => 'Check: ' . $r['note']['title'],
            'r' => $r,
            'errors' => $errors,
            'old' => $old,
            'names' => Subjects::NAMES,
        ]), $status);
    }

    private function signaturePage(string $error, string $back, int $status = 200): Response
    {
        return Response::html($this->view->render('partner/signature', [
            'title' => 'Your signature',
            'me' => $this->reviews->reviewer((int) $this->context->userId()) ?? throw new HttpException(403),
            'error' => $error,
            'back' => preg_match('#^/partner/reviews(/\d+)?$#', $back) ? $back : '/partner/reviews',
        ]), $status);
    }
}
