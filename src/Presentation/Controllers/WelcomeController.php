<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admissions\AccountSetupService;
use SmartCircle\Application\Admissions\AdmissionException;
use SmartCircle\Application\Admissions\Pledge;
use SmartCircle\Application\Admissions\SignupForm;
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
        return $this->onboardingPage([], []);
    }

    public function completeOnboarding(Request $request): Response
    {
        $input = [];
        foreach (['study_mode', 'school_area', 'travel_time', 'home_study', 'signed_name', 'guardian'] as $f) {
            $input[$f] = $request->input($f);
        }
        $input['vows'] = $request->inputList('vows');
        $errors = $this->setup->completeOnboarding((int) $this->context->userId(), $input, $request->ip());
        if ($errors !== []) {
            return $this->onboardingPage($errors, $input, 422);
        }
        $this->session->flash('success', 'Pledge signed. Welcome to SMART Circle! Your pledge certificate is on your dashboard.');

        return Response::redirect('/dashboard');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function onboardingPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('welcome/onboarding', [
            'title' => 'Welcome to SMART Circle',
            'profile' => $this->setup->profile((int) $this->context->userId()),
            'user' => $this->context->user(),
            'errors' => $errors,
            'old' => $old,
            'modes' => SignupForm::STUDY_MODES,
            'travel' => SignupForm::TRAVEL,
            'homeStudy' => SignupForm::HOME_STUDY,
            'vows' => Pledge::VOWS,
        ]), $status);
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
