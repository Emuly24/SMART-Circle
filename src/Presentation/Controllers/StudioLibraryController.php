<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Library\LibraryService;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;

/** Tutor and team: choose what goes into the free public library (routes guarded by SubjectGuard). */
final class StudioLibraryController
{
    public function __construct(
        private readonly LibraryService $library,
        private readonly Session $session,
    ) {
    }

    public function note(Request $request): Response
    {
        $id = (int) $request->param('id');
        $on = match ($request->param('action')) {
            'on' => true,
            'off' => false,
            default => throw new HttpException(404),
        };
        $this->library->setNoteInLibrary($id, $on);
        $this->session->flash('success', $on
            ? 'Added to the free library. Once published, anyone can read its text and pictures (never the exercises).'
            : 'Taken out of the free library.');

        return Response::redirect('/studio/notes/' . $id);
    }

    public function upload(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->library->attachHandout($id, $request->file('handout'));
        $this->session->flash(...($error === '' ? ['success', 'Handout uploaded. Tick "Show in the free library" to share it with everyone.'] : ['error', $error]));

        return Response::redirect('/studio/lessons/' . $id . '#handout');
    }

    public function handout(Request $request): Response
    {
        $id = (int) $request->param('id');
        match ($request->param('action')) {
            'public' => $this->library->setHandoutPublic($id, true),
            'private' => $this->library->setHandoutPublic($id, false),
            'remove' => $this->library->removeHandout($id),
            default => throw new HttpException(404),
        };
        $this->session->flash('info', ['public' => 'The handout is in the free library (once the lesson is published).', 'private' => 'The handout is no longer in the free library.', 'remove' => 'Handout removed.'][$request->param('action')]);

        return Response::redirect('/studio/lessons/' . $id . '#handout');
    }

    /** The tutor's own copy of the handout, to check it. */
    public function download(Request $request): Response
    {
        $h = $this->library->tutorHandout((int) $request->param('id')) ?? throw new HttpException(404);

        return Response::files([$h], 'application/pdf', (int) filesize($h))->withHeader('Content-Disposition', 'inline; filename="handout.pdf"');
    }
}
