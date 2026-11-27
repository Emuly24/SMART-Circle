<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Application\Reviews\LessonReviewService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Partner teachers check recorded lessons (and their handouts); the tutor asks; approvals show a signature. */
final class LessonReviewController
{
    public function __construct(
        private readonly LessonReviewService $reviews,
        private readonly LessonService $lessons,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
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
        $this->session->flash('success', $r['decision'] === 'approved' ? 'Thank you. Your approval and signature now appear on this lesson.' : 'Thank you. The tutor has your suggestions.');

        return Response::redirect('/partner/reviews');
    }

    public function file(Request $request): Response
    {
        $id = (int) $request->param('id');
        $r = $this->reviews->forReviewer((int) $this->context->userId(), $id) ?? throw new HttpException(404);
        $parts = $this->lessons->fileParts($id);
        if ($parts === []) {
            throw new HttpException(404);
        }

        return Response::files($parts, 'application/octet-stream', (int) $r['lesson']['size_bytes']);
    }

    public function handout(Request $request): Response
    {
        $path = $this->reviews->handoutFor((int) $this->context->userId(), (int) $request->param('id')) ?? throw new HttpException(404);

        return Response::files([$path], 'application/pdf', (int) filesize($path))->withHeader('Content-Disposition', 'inline; filename="handout.pdf"');
    }

    public function request(Request $request): Response
    {
        $id = (int) $request->param('id');
        $r = $this->reviews->requestReview($id);
        $this->session->flash(...($r['error'] !== '' ? ['info', $r['error']] : ['success', 'Sent to ' . $r['sent'] . ' partner teacher' . ($r['sent'] === 1 ? '' : 's') . ' to check.']));

        return Response::redirect('/studio/lessons/' . $id . '#reviews');
    }

    public function signature(Request $request): Response
    {
        $s = $this->reviews->approvalSignature((int) $request->param('id')) ?? throw new HttpException(404);

        return Response::files([$s['path']], 'image/png', (int) filesize($s['path']))->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function page(int $id, array $errors, array $old, int $status = 200): Response
    {
        $r = $this->reviews->forReviewer((int) $this->context->userId(), $id) ?? throw new HttpException(404);

        return Response::html($this->view->render('partner/review_lesson', [
            'title' => 'Check: ' . $r['lesson']['title'],
            'r' => $r,
            'errors' => $errors,
            'old' => $old,
            'names' => Subjects::NAMES,
            'sections' => LessonService::REAL_LIFE_SECTIONS,
        ]), $status);
    }
}
