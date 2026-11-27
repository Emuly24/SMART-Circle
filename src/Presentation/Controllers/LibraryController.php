<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Library\LibraryService;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** The free public library: no account needed. */
final class LibraryController
{
    public function __construct(
        private readonly LibraryService $library,
        private readonly View $view,
        private readonly string $appUrl,
    ) {
    }

    public function index(Request $request): Response
    {
        $f = LibraryService::filters((string) $request->query('form'), (string) $request->query('subject'));

        return Response::html($this->view->render('library/index', [
            'title' => 'Free library',
            'filters' => $f,
            'names' => Subjects::NAMES,
        ] + $this->library->browse($f['form'], $f['subject'])));
    }

    public function note(Request $request): Response
    {
        return Response::html($this->view->render('library/note', $this->noteData($request) + ['print' => false]));
    }

    public function printNote(Request $request): Response
    {
        return Response::html($this->view->render('library/note', $this->noteData($request) + ['print' => true], 'layouts/print'));
    }

    public function image(Request $request): Response
    {
        $img = $this->library->image((int) $request->param('id'), (int) $request->param('block')) ?? throw new HttpException(404);

        return Response::files([$img['path']], $img['mime'], (int) filesize($img['path']))->withHeader('Cache-Control', 'public, max-age=86400');
    }

    public function handout(Request $request): Response
    {
        $h = $this->library->handoutFile((int) $request->param('id')) ?? throw new HttpException(404);

        return Response::files([$h['path']], 'application/pdf', (int) filesize($h['path']))
            ->withHeader('Content-Disposition', 'inline; filename="' . $h['name'] . '"')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** @return array<string, mixed> */
    private function noteData(Request $request): array
    {
        $note = $this->library->note((int) $request->param('id')) ?? throw new HttpException(404);

        $site = (string) preg_replace('#^https?://#', '', rtrim($this->appUrl, '/'));

        return ['title' => (string) $note['title'], 'note' => $note, 'names' => Subjects::NAMES, 'applyAt' => $site === '' ? '' : $site . '/apply'];
    }
}
