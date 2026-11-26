<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Alumni\AlumniService;
use SmartCircle\Application\Alumni\YearEndService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Alumni: profile, community board, and the tutor's alumni list and year-end page. */
final class AlumniController
{
    public function __construct(
        private readonly AlumniService $alumni,
        private readonly YearEndService $yearEnd,
        private readonly \Closure $studentForm,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function home(Request $request): Response
    {
        return $this->homePage([], []);
    }

    public function saveProfile(Request $request): Response
    {
        $in = ['now_doing' => $request->input('now_doing'), 'institution' => $request->input('institution'), 'programme' => $request->input('programme'),
            'needs' => $request->inputList('needs'), 'can_mentor' => $request->input('can_mentor'), 'mentor_note' => $request->input('mentor_note')];
        $errors = $this->alumni->saveProfile($this->uid(), $in);
        if ($errors !== []) {
            return $this->homePage($errors, $in, 422);
        }
        $this->session->flash('success', 'Saved. Thank you for keeping us up to date.');

        return Response::redirect('/alumni');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function homePage(array $errors, array $old, int $status = 200): Response
    {
        $p = $this->alumni->profile($this->uid()) ?? throw new HttpException(404);

        return Response::html($this->view->render('alumni/home', [
            'title' => 'Alumni',
            'p' => $p,
            'doing' => AlumniService::NOW_DOING,
            'needs' => AlumniService::NEEDS,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    public function community(Request $request): Response
    {
        $this->mustRead();

        return Response::html($this->view->render('alumni/community', [
            'title' => 'Alumni community',
            'posts' => $this->alumni->posts($this->role()),
            'kinds' => AlumniService::KINDS,
            'canWrite' => AlumniService::mayWrite($this->role()),
            'isTutor' => $this->role() === 'tutor',
            'errors' => [],
            'old' => [],
        ]));
    }

    public function createPost(Request $request): Response
    {
        $in = ['kind' => $request->input('kind'), 'title' => $request->input('title'), 'body' => $request->input('body')];
        $r = $this->alumni->createPost($this->uid(), $this->role(), $in['kind'], $in['title'], $in['body']);
        if ($r['errors'] !== []) {
            return Response::html($this->view->render('alumni/community', [
                'title' => 'Alumni community',
                'posts' => $this->alumni->posts($this->role()),
                'kinds' => AlumniService::KINDS,
                'canWrite' => AlumniService::mayWrite($this->role()),
                'isTutor' => $this->role() === 'tutor',
                'errors' => $r['errors'],
                'old' => $in,
            ]), 422);
        }

        return Response::redirect('/alumni/community/' . $r['id']);
    }

    public function post(Request $request): Response
    {
        return $this->postPage((int) $request->param('id'), '', null);
    }

    public function reply(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->alumni->reply($id, $this->uid(), $this->role(), $request->input('body'));
        if ($error !== null) {
            return $this->postPage($id, $request->input('body'), $error, 422);
        }

        return Response::redirect('/alumni/community/' . $id . '#latest');
    }

    private function postPage(int $id, string $draft, ?string $error, int $status = 200): Response
    {
        $this->mustRead();
        $p = $this->alumni->post($id, $this->role()) ?? throw new HttpException(404);

        return Response::html($this->view->render('alumni/post', [
            'title' => (string) $p['post']['title'],
            'p' => $p,
            'kinds' => AlumniService::KINDS,
            'canWrite' => AlumniService::mayWrite($this->role()),
            'isTutor' => $this->role() === 'tutor',
            'draft' => $draft,
            'error' => $error,
        ]), $status);
    }

    public function moderate(Request $request): Response
    {
        $postId = $this->alumni->moderate($request->input('what'), (int) $request->param('id'), $request->input('action'));
        if ($postId === null) {
            throw new HttpException(404);
        }
        $this->session->flash('success', 'Done.');

        return Response::redirect('/alumni/community/' . $postId);
    }

    public function studio(Request $request): Response
    {
        return Response::html($this->view->render('studio/alumni', [
            'title' => 'Alumni',
            'people' => $this->alumni->everyone(),
            'doing' => AlumniService::NOW_DOING,
            'needs' => AlumniService::NEEDS,
        ]));
    }

    public function yearEnd(Request $request): Response
    {
        return $this->yearEndPage([], []);
    }

    public function applyYearEnd(Request $request): Response
    {
        $choices = (array) ($request->allInput()['s'] ?? []);
        $r = $this->yearEnd->apply($choices, $this->uid(), $request->ip());
        if ($r['errors'] !== []) {
            return $this->yearEndPage($r['errors'], $choices, 422);
        }
        $this->session->flash('success', $r['promoted'] . ' moved up a form, ' . $r['graduated'] . ' joined the alumni.');

        return Response::redirect('/studio/year-end');
    }

    /** @param array<int, string> $errors @param array<string, mixed> $old */
    private function yearEndPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/year_end', [
            'title' => 'End of the school year',
            'students' => $this->yearEnd->students(),
            'schools' => $this->yearEnd->schools(),
            'actions' => YearEndService::ACTIONS,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    private function mustRead(): void
    {
        $form = $this->role() === 'student' ? ($this->studentForm)($this->uid()) : null;
        if (!AlumniService::mayRead($this->role(), $form)) {
            throw new HttpException(403, 'The alumni community opens in Form 3.');
        }
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
