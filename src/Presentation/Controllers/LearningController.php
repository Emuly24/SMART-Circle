<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Learning\FeedbackService;
use SmartCircle\Application\Learning\ImpactService;
use SmartCircle\Application\Learning\Quick5Service;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Quick 5 and termly feedback (students); the impact and feedback reports (admin). */
final class LearningController
{
    public function __construct(
        private readonly Quick5Service $quick5,
        private readonly FeedbackService $feedback,
        private readonly ImpactService $impact,
        private readonly \Closure $studentForm,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function quick5(Request $request): Response
    {
        $q = $this->quick5->forStudent((int) $this->context->userId()) ?? throw new HttpException(403);

        return Response::html($this->view->render('learning/quick5', ['title' => 'Quick 5', 'q' => $q, 'results' => null, 'names' => Subjects::NAMES]));
    }

    public function check(Request $request): Response
    {
        $answers = (array) ($request->allInput()['answer'] ?? []);
        $results = $this->quick5->check((int) $this->context->userId(), $answers) ?? throw new HttpException(403);
        $q = $this->quick5->forStudent((int) $this->context->userId());

        return Response::html($this->view->render('learning/quick5', ['title' => 'Quick 5', 'q' => $q, 'results' => $results, 'names' => Subjects::NAMES]));
    }

    public function feedbackForm(Request $request): Response
    {
        return $this->feedbackPage([], []);
    }

    public function sendFeedback(Request $request): Response
    {
        $in = ['clarity' => $request->input('clarity'), 'pace' => $request->input('pace'), 'helpful' => $request->input('helpful'), 'change' => $request->input('change')];
        $errors = $this->feedback->submit((int) $this->context->userId(), ($this->studentForm)((int) $this->context->userId()), $in);
        if ($errors !== []) {
            return $this->feedbackPage($errors, $in, 422);
        }
        $this->session->flash('success', 'Thank you! Your feedback is anonymous and helps make the lessons better.');

        return Response::redirect('/dashboard');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function feedbackPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('learning/feedback', [
            'title' => 'How are the lessons going?',
            'open' => $this->feedback->shouldAsk((int) $this->context->userId()),
            'pace' => FeedbackService::PACE,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    public function impact(Request $request): Response
    {
        return Response::html($this->view->render('studio/impact', ['title' => 'Impact', 'r' => $this->impact->report()]));
    }

    public function feedbackReport(Request $request): Response
    {
        $term = $request->query('term');

        return Response::html($this->view->render('studio/feedback', [
            'title' => 'Student feedback',
            'r' => $this->feedback->report($term !== '' ? (int) $term : null),
            'pace' => FeedbackService::PACE,
            'min' => FeedbackService::MIN_GROUP,
        ]));
    }
}
