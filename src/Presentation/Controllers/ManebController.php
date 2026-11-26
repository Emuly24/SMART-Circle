<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Maneb\ManebService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** MANEB revision plan, sharing results, and the public results summary. */
final class ManebController
{
    public function __construct(
        private readonly ManebService $maneb,
        private readonly \Closure $profile,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function revision(Request $request): Response
    {
        return Response::html($this->view->render('maneb/revision', [
            'title' => 'MANEB revision',
            'plan' => $this->maneb->plan((int) $this->context->userId()),
            'names' => Subjects::NAMES,
        ]));
    }

    public function results(Request $request): Response
    {
        return $this->resultsPage([], []);
    }

    public function saveResults(Request $request): Response
    {
        $in = ['exam' => $request->input('exam'), 'year' => $request->input('year'), 'grades' => $request->allInput()['grades'] ?? [],
            'subjects_sat' => $request->input('subjects_sat'), 'subjects_passed' => $request->input('subjects_passed'),
            'points' => $request->input('points'), 'reflection' => $request->input('reflection'), 'share' => $request->input('share')];
        $r = $this->maneb->save((int) $this->context->userId(), (string) $this->context->role(), $in);
        if ($r['errors'] !== []) {
            return $this->resultsPage($r['errors'], $in, 422);
        }
        $this->session->flash('success', 'Thank you for sharing your results. Congratulations on finishing the exam!');

        return Response::redirect('/results/mine');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function resultsPage(array $errors, array $old, int $status = 200): Response
    {
        $profile = ($this->profile)((int) $this->context->userId());
        $exams = ManebService::examsFor((string) $this->context->role(), $profile);
        if ($exams === []) {
            throw new HttpException(403, 'MANEB results are recorded from Form 2.');
        }

        return Response::html($this->view->render('maneb/mine', [
            'title' => 'My MANEB results',
            'results' => $this->maneb->mine((int) $this->context->userId()),
            'exams' => array_intersect_key(ManebService::EXAMS, array_flip($exams)),
            'grades' => ManebService::GRADES,
            'share' => ManebService::SHARE,
            'subjects' => Subjects::forTrack((string) ($profile['track'] ?? 'general')),
            'names' => Subjects::NAMES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    public function public(Request $request): Response
    {
        return Response::html($this->view->render('maneb/public', [
            'title' => 'MANEB results',
            'groups' => $this->maneb->publicSummary(),
            'exams' => ManebService::EXAMS,
            'names' => Subjects::NAMES,
        ]));
    }

    public function studio(Request $request): Response
    {
        return Response::html($this->view->render('studio/maneb', [
            'title' => 'MANEB: revision and results',
            'results' => $this->maneb->all(),
            'revising' => $this->maneb->inRevision(),
            'exams' => ManebService::EXAMS,
            'share' => ManebService::SHARE,
            'names' => Subjects::NAMES,
        ]));
    }

    public function verify(Request $request): Response
    {
        $ok = $this->maneb->verify((int) $request->param('id'), (int) $this->context->userId());
        $this->session->flash(...($ok ? ['success', 'Marked as checked against the result slip.'] : ['error', 'Result not found.']));

        return Response::redirect('/studio/results');
    }
}
