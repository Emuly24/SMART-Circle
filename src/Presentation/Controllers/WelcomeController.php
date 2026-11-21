<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admissions\AccountSetupService;
use SmartCircle\Application\Admissions\AdmissionException;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/**
 * New students: phone + tutor's code -> choose password -> agreement & guardian -> dashboard.
 * The same first two steps reset a forgotten password.
 */
final class WelcomeController
{
    private const KEY = 'welcome';

    public function __construct(
        private readonly AccountSetupService $setup,
        private readonly Session $session,
        private readonly Context $context,
        private readonly View $view,
    ) {
    }

    public function codeForm(Request $request): Response
    {
        return Response::html($this->view->render('welcome/code', ['title' => 'Set up your account', 'error' => '', 'phone' => '']));
    }

    public function checkCode(Request $request): Response
    {
        try {
            $ok = $this->setup->checkCode($request->input('phone'), $request->input('code'));
        } catch (AdmissionException $e) {
            return Response::html($this->view->render('welcome/code', ['title' => 'Set up your account', 'error' => $e->getMessage(), 'phone' => $request->input('phone')]), 422);
        }
        $this->session->set(self::KEY, $ok + ['at' => time()]);

        return Response::redirect('/welcome/password');
    }

    public function passwordForm(Request $request): Response
    {
        if ($this->pending() === null) {
            return Response::redirect('/welcome');
        }

        return Response::html($this->view->render('welcome/password', ['title' => 'Choose a password', 'error' => '']));
    }

    public function setPassword(Request $request): Response
    {
        $pending = $this->pending();
        if ($pending === null) {
            return Response::redirect('/welcome');
        }
        $error = $this->setup->setPassword($pending['user_id'], $pending['code_id'], $request->input('password'), $request->input('password_confirm'), $request->ip());
        if ($error !== '') {
            return Response::html($this->view->render('welcome/password', ['title' => 'Choose a password', 'error' => $error]), 422);
        }
        $this->session->forget(self::KEY);
        $this->session->login($pending['user_id']);

        return Response::redirect($this->setup->needsOnboarding($pending['user_id']) ? '/welcome/onboarding' : '/dashboard');
    }

    public function onboardingForm(Request $request): Response
    {
        $profile = $this->setup->profile((int) $this->context->userId());

        return Response::html($this->view->render('welcome/onboarding', ['title' => 'Welcome to SMART Circle', 'profile' => $profile, 'error' => '']));
    }

    public function completeOnboarding(Request $request): Response
    {
        if ($request->input('agreement') !== '1' || $request->input('guardian') !== '1') {
            return Response::html($this->view->render('welcome/onboarding', [
                'title' => 'Welcome to SMART Circle',
                'profile' => $this->setup->profile((int) $this->context->userId()),
                'error' => 'Please tick both boxes to continue.',
            ]), 422);
        }
        $this->setup->completeOnboarding((int) $this->context->userId());
        $this->session->flash('success', 'You are all set. Welcome to SMART Circle!');

        return Response::redirect('/dashboard');
    }

    /** @return array{user_id: int, code_id: int}|null valid for 15 minutes after the code check */
    private function pending(): ?array
    {
        $p = $this->session->get(self::KEY);
        if (!is_array($p) || time() - (int) ($p['at'] ?? 0) > 900) {
            return null;
        }

        return ['user_id' => (int) $p['user_id'], 'code_id' => (int) $p['code_id']];
    }
}
