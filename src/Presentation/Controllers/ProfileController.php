<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Profile\TitleService;
use SmartCircle\Application\Mail\EmailSettings;
use SmartCircle\Application\Profile\AvatarService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Every member's profile page and profile picture. */
final class ProfileController
{
    public function __construct(
        private readonly AvatarService $avatars,
        private readonly \Closure $avatarAt,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?EmailSettings $emailSettings = null,
        private readonly ?TitleService $titles = null,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->view->render('profile/show', [
            'title' => 'My profile',
            'user' => $this->context->user(),
            'error' => null,
        ]));
    }

    public function saveEmail(Request $request): Response
    {
        $error = $this->emailSettings?->save((int) $this->context->userId(), $request->input('email'), $request->input('email_notifications') === '1') ?? '';
        if ($error !== '') {
            return Response::html($this->view->render('profile/show', ['title' => 'My profile', 'user' => $this->context->user(), 'error' => null, 'emailError' => $error]), 422);
        }
        $this->session->flash('success', 'Email settings saved.');

        return Response::redirect('/profile#email');
    }

    /** Teachers and tutors: how to address them (Mr, Mrs, Dr ...). */
    public function saveTitle(Request $request): Response
    {
        $error = $this->titles?->save((int) $this->context->userId(), $request->input('title'));
        if ($error !== null) {
            return Response::html($this->view->render('profile/show', ['title' => 'My profile', 'user' => $this->context->user(), 'error' => null, 'titleError' => $error]), 422);
        }
        $this->session->flash('success', 'Saved. Messages and emails can now greet you by your title.');

        return Response::redirect('/profile#title');
    }

    /** The tutor sets how a partner teacher or team tutor is addressed (they can change it in their profile). */
    public function setTitleFor(Request $request): Response
    {
        $id = (int) $request->param('id');
        $back = $request->input('back');
        $back = preg_match('#^/studio/(partners|team)/[0-9]+$#', $back) ? $back : '/studio/partners';
        $error = $this->titles?->saveFor($id, $request->input('title'));
        $this->session->flash($error === null ? 'success' : 'error', $error ?? 'Title saved.');

        return Response::redirect($back);
    }

    public function savePhoto(Request $request): Response
    {
        $error = $this->avatars->save((int) $this->context->userId(), $request->file('photo'));
        if ($error !== null) {
            return Response::html($this->view->render('profile/show', ['title' => 'My profile', 'user' => $this->context->user(), 'error' => $error]), 422);
        }
        $this->session->flash('success', 'Your photo is saved.');

        return Response::redirect('/profile');
    }

    public function removePhoto(Request $request): Response
    {
        $this->avatars->remove((int) $this->context->userId());
        $this->session->flash('info', 'Your photo was removed.');

        return Response::redirect('/profile');
    }

    /** Members only; photos live outside the web root. */
    public function photo(Request $request): Response
    {
        $id = (int) $request->param('id');
        $path = $this->avatars->path($id);
        if (($this->avatarAt)($id) === null || !is_file($path)) {
            throw new HttpException(404);
        }

        return Response::files([$path], 'image/jpeg', (int) filesize($path));
    }
}
