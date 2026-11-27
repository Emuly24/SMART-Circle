<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Notes\NoteService;
use SmartCircle\Application\Reviews\NoteReviewService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Students: read notes and slides, doing each checkpoint before the next part opens. */
final class NoteController
{
    public function __construct(
        private readonly NoteService $notes,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?NoteReviewService $reviews = null,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('notes/index', [
            'title' => 'Notes & slides',
            'items' => $this->notes->listFor((int) $this->context->userId()),
            'names' => Subjects::NAMES,
        ]));
    }

    public function show(Request $request): Response
    {
        $r = $this->notes->read((int) $request->param('id'), (int) $this->context->userId()) ?? throw new HttpException(404);

        return Response::html($this->view->render('notes/show', [
            'title' => (string) $r['note']['title'],
            'r' => $r,
            'names' => Subjects::NAMES,
            'approval' => $r['locked'] ? null : $this->reviews?->approval((int) $r['note']['id']),
        ]), $r['locked'] ? 403 : 200);
    }

    public function submit(Request $request): Response
    {
        $noteId = (int) $request->param('id');
        $blockId = (int) $request->param('block');
        $all = $request->allInput();
        $res = $this->notes->submitCheckpoint($noteId, $blockId, (int) $this->context->userId(), is_array($all['answer'] ?? null) ? $all['answer'] : []);
        if ($res['error'] !== '') {
            $this->session->flash('error', $res['error']);
        } else {
            $this->session->flash('success', $res['state'] === 'results'
                ? 'Well done! Exercise submitted and marked. The next part is open below.'
                : 'Well done! Exercise submitted. The tutor will mark your written answers soon. The next part is open below.');
        }

        return Response::redirect('/notes/' . $noteId . '#block-' . $blockId);
    }

    public function image(Request $request): Response
    {
        $path = $this->notes->imagePath((int) $request->param('id'), (int) $request->param('block'), $this->context->userId(), false)
            ?? throw new HttpException(404);

        return self::imageResponse($path);
    }

    public static function imageResponse(string $path): Response
    {
        $type = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Response::files([$path], $type, (int) filesize($path))->withHeader('Cache-Control', 'private, max-age=86400');
    }
}
