<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Auth\AuthService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Session $session,
        private readonly Context $context,
        private readonly View $view,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->view->render('auth/login', [
            'title' => 'Log in',
            'phone' => '',
            'error' => '',
            'installed' => $request->query('installed') === '1',
        ]));
    }

    public function login(Request $request): Response
    {
        $phone = trim($request->input('phone'));
        $result = $this->auth->attempt($phone, $request->input('password'), $request->ip());

        if (!$result->ok || $result->userId === null) {
            return Response::html(
                $this->view->render('auth/login', ['title' => 'Log in', 'phone' => $phone, 'error' => $result->error, 'installed' => false]),
                422
            );
        }

        $this->session->login($result->userId);
        $intended = $this->session->get('intended');
        $this->session->forget('intended');

        // Only same-site paths, never a full URL (prevents open redirects).
        $target = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')
            ? $intended
            : '/dashboard';

        return Response::redirect($target);
    }

    public function logout(Request $request): Response
    {
        $userId = $this->context->userId();
        if ($userId !== null) {
            $this->auth->recordLogout($userId, $request->ip());
        }
        $this->session->logout();

        return Response::redirect('/');
    }
}
