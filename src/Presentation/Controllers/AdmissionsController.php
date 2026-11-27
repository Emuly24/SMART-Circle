<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admissions\AdmissionException;
use SmartCircle\Application\Admissions\ApplicationForm;
use SmartCircle\Application\Admissions\ApplicationService;
use SmartCircle\Application\Admissions\DecisionEmail;
use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Application\Admissions\ReviewService;
use SmartCircle\Application\Mail\MailService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Tutor: seat chart, applications, decisions, intake settings. */
final class AdmissionsController
{
    public function __construct(
        private readonly ReviewService $review,
        private readonly ApplicationService $applications,
        private readonly DocumentService $documents,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?MailService $mail = null,
        private readonly string $siteUrl = '',
    ) {
    }

    public function index(Request $request): Response
    {
        $intake = $this->review->currentIntake();
        if ($intake === null) {
            return Response::redirect('/studio/admissions/intake');
        }
        $filter = [
            'status' => in_array($request->query('status'), ['submitted', 'shortlisted', 'admitted', 'waitlisted', 'declined'], true) ? $request->query('status') : '',
            'form' => in_array((int) $request->query('form'), [1, 2, 3, 4], true) ? (int) $request->query('form') : 0,
        ];

        return Response::html($this->view->render('studio/admissions/index', [
            'title' => 'Admissions',
            'intake' => $intake,
            'chart' => $this->review->seatChart($intake),
            'applications' => $this->review->applications($intake, $filter),
            'filter' => $filter,
            'isOpen' => $this->applications->openIntake() !== null,
        ]));
    }

    public function show(Request $request): Response
    {
        $app = $this->application($request);
        $intake = $this->review->currentIntake();

        return Response::html($this->view->render('studio/admissions/show', [
            'title' => $app['full_name'],
            'app' => $app,
            'answers' => $this->review->quizAnswers((int) $app['id']),
            'letters' => $this->documents->forApplication((int) $app['id']),
            'hasSeat' => $intake !== null && $this->review->seatChart($intake)->hasSeat((int) $app['form'], (string) $app['gender']),
            'subjects' => Subjects::NAMES,
            'tracks' => Subjects::TRACKS,
            'devices' => ApplicationForm::DEVICES,
            'dataAccess' => ApplicationForm::DATA_ACCESS,
            'setupCode' => $this->session->get('setup_code_' . $app['id']),
        ]));
    }

    public function decide(Request $request): Response
    {
        $app = $this->application($request);
        try {
            $result = $this->review->decide((int) $app['id'], $request->input('decision'), $request->input('note'), (int) $this->context->userId(), $request->ip());
        } catch (AdmissionException $e) {
            $this->session->flash('error', $e->getMessage());
            return Response::redirect('/studio/admissions/' . $app['id']);
        }

        $email = DecisionEmail::build($app, $result['status'], $this->siteUrl, $result['letter_code']);
        if ($email !== null && $this->mail !== null && (string) ($app['email'] ?? '') !== '') {
            $this->mail->queue((string) $app['email'], (string) $app['full_name'], $email['subject'], $email['text'], 'decision_' . $result['status'], 'application', (int) $app['id'], (int) $this->context->userId());
        }

        if ($result['setup_code'] !== null) {
            // Shown once on the next page so the tutor can give it to the student.
            $this->session->set('setup_code_' . $app['id'], $result['setup_code']);
            $this->session->flash('success', 'Admitted. A student account was created. Give the student the setup code below.');
        } else {
            $this->session->flash('success', 'Decision saved: ' . $result['status'] . '.');
        }

        return Response::redirect('/studio/admissions/' . $app['id']);
    }

    public function newCode(Request $request): Response
    {
        $app = $this->application($request);
        if ($app['user_id'] === null) {
            throw new HttpException(404);
        }
        $code = $this->review->issueCode((int) $app['user_id'], 'setup', (int) $this->context->userId());
        $this->session->set('setup_code_' . $app['id'], $code);
        $this->session->flash('success', 'A new setup code was made. Any older code no longer works.');

        return Response::redirect('/studio/admissions/' . $app['id']);
    }

    public function forgetCode(Request $request): Response
    {
        $app = $this->application($request);
        $this->session->forget('setup_code_' . $app['id']);

        return Response::redirect('/studio/admissions/' . $app['id']);
    }

    public function report(Request $request): Response
    {
        $app = $this->application($request);
        $path = $this->applications->reportPath($app);
        if ($path === null || !is_file($path)) {
            throw new HttpException(404);
        }

        return Response::files([$path], (string) $app['report_mime'], (int) filesize($path))
            ->withHeader('Content-Disposition', 'inline; filename="report-' . $app['reference'] . '"');
    }

    public function intakeForm(Request $request): Response
    {
        return Response::html($this->view->render('studio/admissions/intake', [
            'title' => 'Intake settings',
            'intake' => $this->review->currentIntake(),
            'chart' => ($i = $this->review->currentIntake()) !== null ? $this->review->seatChart($i) : null,
        ]));
    }

    public function intakeSave(Request $request): Response
    {
        $intake = $this->review->currentIntake();
        if ($request->input('action') === 'new' || $intake === null) {
            $this->review->newIntake($request->input('name'), (int) $request->input('seats_per_group', '3'));
            $this->session->flash('success', 'New intake started. Applications are open.');
            return Response::redirect('/studio/admissions');
        }

        $closes = trim($request->input('closes_at'));
        $closesAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $closes) ? $closes . ' 23:59:59' : null;
        $active = $request->input('is_open') === '1';
        $plan = $request->allInput()['plan'] ?? null;
        $this->review->updateIntake((int) $intake['id'], $request->input('name'), $active ? $closesAt : date('Y-m-d H:i:s'),
            (int) $request->input('seats_per_group', '3'), true, is_array($plan) ? $plan : null);
        $this->session->flash('success', $active ? 'Intake saved. Applications are open.' : 'Intake saved. Applications are now closed.');

        return Response::redirect('/studio/admissions');
    }

    /** @return array<string, mixed> */
    private function application(Request $request): array
    {
        return $this->review->find((int) $request->param('id')) ?? throw new HttpException(404);
    }
}
