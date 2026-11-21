<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admissions\AdmissionException;
use SmartCircle\Application\Admissions\ApplicationForm;
use SmartCircle\Application\Admissions\ApplicationService;
use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Malawi;
use SmartCircle\Support\Subjects;

/**
 * Public admissions pages: apply, entry quiz, confirmation, status check.
 */
final class ApplyController
{
    private const REF_KEY = 'apply_ref';
    private const STARTED_KEY = 'apply_started';

    public function __construct(
        private readonly ApplicationService $applications,
        private readonly DocumentService $documents,
        private readonly Session $session,
        private readonly Context $context,
        private readonly View $view,
    ) {
    }

    public function show(Request $request): Response
    {
        $intake = $this->applications->openIntake();
        if ($intake === null) {
            return Response::html($this->view->render('apply/closed', ['title' => 'Applications closed', 'chart' => $this->applications->seatChart()]));
        }
        $this->session->set(self::STARTED_KEY, time());

        return $this->form([], []);
    }

    public function submit(Request $request): Response
    {
        $input = $request->allInput();
        $input['subjects_taken'] = $request->inputList('subjects_taken');
        $input['help_subjects'] = $request->inputList('help_subjects');
        $started = (int) $this->session->get(self::STARTED_KEY, time());

        $result = $this->applications->submit(
            $input,
            $request->file('report'),
            $request->ip(),
            time() - $started,
            $this->context->role() === 'tutor',
        );

        if ($result['errors'] !== []) {
            return $this->form($result['errors'], $input, 422);
        }

        $this->session->forget(self::STARTED_KEY);
        $this->session->set(self::REF_KEY, $result['reference']);

        return Response::redirect('/apply/quiz');
    }

    public function quiz(Request $request): Response
    {
        $app = $this->sessionApplication();
        if (!$this->applications->canTakeQuiz($app)) {
            return Response::redirect('/apply/done');
        }

        return Response::html($this->view->render('apply/quiz', [
            'title' => 'Entry quiz',
            'app' => $app,
            'questions' => $this->applications->startQuiz($app),
            'minutes' => ApplicationService::QUIZ_MINUTES,
        ]));
    }

    public function submitQuiz(Request $request): Response
    {
        $app = $this->sessionApplication();
        $answers = [];
        foreach ($request->allInput() as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'q') && is_string($value)) {
                $answers[substr($key, 1)] = $value;
            }
        }
        try {
            $this->applications->submitQuiz($app, $answers);
        } catch (AdmissionException) {
            // Already submitted (e.g. double tap): just show the confirmation.
        }

        return Response::redirect('/apply/done');
    }

    public function done(Request $request): Response
    {
        $app = $this->sessionApplication();

        return Response::html($this->view->render('apply/done', ['title' => 'Application received', 'app' => $app]));
    }

    public function statusForm(Request $request): Response
    {
        return Response::html($this->view->render('apply/status-form', ['title' => 'Check my application', 'error' => '', 'reference' => '', 'phone' => '']));
    }

    public function status(Request $request): Response
    {
        $reference = trim($request->input('reference'));
        $phone = trim($request->input('phone'));
        $app = $this->applications->findForStatus($reference, $phone);

        if ($app === null) {
            return Response::html($this->view->render('apply/status-form', [
                'title' => 'Check my application',
                'error' => 'We could not find an application with that reference and phone number.',
                'reference' => $reference,
                'phone' => $phone,
            ]), 404);
        }

        // Remember this application for the quiz and letter pages.
        $this->session->set(self::REF_KEY, $app['reference']);

        return Response::html($this->view->render('apply/status', [
            'title' => 'My application',
            'app' => $app,
            'canTakeQuiz' => $this->applications->canTakeQuiz($app),
            'letters' => array_values(array_filter($this->documents->forApplication((int) $app['id']), static fn (array $d): bool => $d['revoked_at'] === null)),
        ]));
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function form(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('apply/form', [
            'title' => 'Apply to SMART Circle',
            'errors' => $errors,
            'old' => $old,
            'chart' => $this->applications->seatChart(),
            'subjects' => Subjects::NAMES,
            'districts' => Malawi::DISTRICTS,
            'devices' => ApplicationForm::DEVICES,
            'dataAccess' => ApplicationForm::DATA_ACCESS,
            'relationships' => ApplicationForm::RELATIONSHIPS,
            'isTutor' => $this->context->role() === 'tutor',
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function sessionApplication(): array
    {
        $ref = $this->session->get(self::REF_KEY);
        $app = is_string($ref) ? $this->applications->findByReference($ref) : null;
        if ($app === null) {
            $this->session->flash('info', 'Enter your reference and phone number to continue your application.');
            throw new HttpException(403, 'Please check your application first.');
        }

        return $app;
    }
}
