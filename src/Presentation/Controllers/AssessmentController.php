<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\ExamService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Students: see, take and get results for exams, assignments and exercises. */
final class AssessmentController
{
    public const STATES = [
        'locked' => 'Opens when your school has taught its topics',
        'not_open' => 'Not open yet',
        'open' => 'Ready to start',
        'in_progress' => 'In progress',
        'submitted' => 'Submitted, waiting for marking',
        'marked' => 'Marked, results coming soon',
        'results' => 'Results ready',
        'missed' => 'Closed',
    ];

    public function __construct(
        private readonly ExamService $exams,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('assessments/index', [
            'title' => 'Exams & assignments',
            'items' => $this->exams->listFor($this->userId()),
            'names' => Subjects::NAMES,
            'kinds' => AssessmentService::KINDS,
            'states' => self::STATES,
        ]));
    }

    public function show(Request $request): Response
    {
        $v = $this->mine($request);

        return Response::html($this->view->render('assessments/show', [
            'title' => (string) $v['assessment']['title'],
            'a' => $v['assessment'],
            'state' => $v['state'],
            'names' => Subjects::NAMES,
            'kinds' => AssessmentService::KINDS,
            'states' => self::STATES,
        ]));
    }

    public function start(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->exams->start($id, $this->userId());
        if ($error !== '') {
            $this->session->flash('error', $error);
            return Response::redirect('/assessments/' . $id);
        }

        return Response::redirect('/assessments/' . $id . '/take');
    }

    public function take(Request $request): Response
    {
        $id = (int) $request->param('id');
        $attempt = $this->exams->attempt($id, $this->userId());
        if ($attempt === null) {
            return Response::redirect('/assessments/' . $id);
        }

        return Response::html($this->view->render('assessments/take', [
            'title' => (string) $attempt['assessment']['title'],
            'at' => $attempt,
            'names' => Subjects::NAMES,
        ]));
    }

    public function save(Request $request): Response
    {
        $id = (int) $request->param('id');
        $all = $request->allInput();
        $final = $request->input('action') === 'submit';
        $r = $this->exams->save($id, $this->userId(), is_array($all['answer'] ?? null) ? $all['answer'] : [], $final);
        if ($r['error'] !== '') {
            $this->session->flash('error', $r['error']);
            return Response::redirect('/assessments/' . $id);
        }
        if ($r['state'] === 'in_progress') {
            $this->session->flash('success', 'Answers saved. You can continue now or later' . ' (before it closes).');
            return Response::redirect('/assessments/' . $id . '/take');
        }
        $this->session->flash($r['late'] ? 'info' : 'success', $r['late']
            ? 'Time was up, so the answers you had saved in time were submitted.'
            : match ($r['state']) {
                'results' => 'Submitted and marked. See your results below.',
                'marked' => 'Submitted. Your marks appear when the tutor releases the results.',
                default => 'Submitted. The tutor will mark your written answers.',
            });

        return Response::redirect('/assessments/' . $id . ($r['state'] === 'results' ? '/result' : ''));
    }

    public function result(Request $request): Response
    {
        $id = (int) $request->param('id');
        $r = $this->exams->result($id, $this->userId());
        if ($r === null) {
            return Response::redirect('/assessments/' . $id);
        }

        return Response::html($this->view->render('assessments/result', [
            'title' => 'Results: ' . $r['assessment']['title'],
            'r' => $r,
            'names' => Subjects::NAMES,
        ]));
    }

    /** @return array{assessment: array<string, mixed>, state: string, submission: ?array<string, mixed>} */
    private function mine(Request $request): array
    {
        return $this->exams->forStudent((int) $request->param('id'), $this->userId()) ?? throw new HttpException(404);
    }

    private function userId(): int
    {
        if ($this->context->role() !== 'student') {
            throw new HttpException(403, 'Exams and assignments are taken by students. Use Studio to manage them.');
        }

        return (int) $this->context->userId();
    }
}
