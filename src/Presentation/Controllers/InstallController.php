<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Install\InstallService;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** One-time setup page: creates the tables and the tutor account. */
final class InstallController
{
    public function __construct(
        private readonly InstallService $installer,
        private readonly Session $session,
        private readonly View $view,
        private readonly string $lockFile,
    ) {
    }

    public function show(Request $request): Response
    {
        $this->guard();

        return Response::html($this->view->render('install/form', [
            'title' => 'Set up SMART Circle',
            'errors' => [],
            'old' => ['full_name' => '', 'phone' => ''],
            'tokenConfigured' => $this->installer->tokenConfigured(),
        ], 'layouts/minimal'));
    }

    public function run(Request $request): Response
    {
        $this->guard();

        $fullName = trim($request->input('full_name'));
        $phone = trim($request->input('phone'));
        $password = $request->input('password');

        $errors = $this->installer->validate(
            $request->input('install_token'),
            $fullName,
            $phone,
            $password,
            $request->input('password_confirm')
        );

        if ($errors !== []) {
            return Response::html($this->view->render('install/form', [
                'title' => 'Set up SMART Circle',
                'errors' => $errors,
                'old' => ['full_name' => $fullName, 'phone' => $phone],
                'tokenConfigured' => $this->installer->tokenConfigured(),
            ], 'layouts/minimal'), 422);
        }

        $this->installer->install($fullName, $phone, $password, $request->ip());
        @file_put_contents($this->lockFile, date('c') . "\n");

        // From the next request on, sessions live in the database (the installer used a
        // temporary file session), so the tutor logs in fresh.
        $this->session->logout();

        return Response::redirect('/login?installed=1');
    }

    private function guard(): void
    {
        if (is_file($this->lockFile) || $this->installer->isInstalled()) {
            throw new HttpException(404);
        }
    }
}
