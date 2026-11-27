<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

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
