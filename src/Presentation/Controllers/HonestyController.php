<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Partners\SchoolService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Tutor: decisions that keep SMART Circle honest, and the report that proves it. */
final class HonestyController
{
    public function __construct(
        private readonly AccessService $access,
        private readonly SchoolService $schools,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function checks(Request $request): Response
    {
        return Response::html($this->view->render('studio/checks', [
            'title' => 'Checks',
            'checks' => $this->access->checks(),
            'names' => Subjects::NAMES,
        ]));
    }

    public function decideException(Request $request): Response
    {
        $approve = $request->input('decision') === 'approve';
        $this->access->decideException((int) $request->input('user_id'), (int) $request->input('topic_id'), $approve, (int) $this->context->userId());
        $this->session->flash('success', $approve ? 'Opened early for this student. They have been told.' : 'Declined. The student has been told it opens when their school teaches it.');

        return Response::redirect('/studio/checks');
    }

    public function reviewReport(Request $request): Response
    {
        $decision = $request->input('decision');
        $this->access->reviewReport(
            (int) $request->input('user_id'),
            (int) $request->input('topic_id'),
            $decision !== 'reject',
            $decision === 'confirm_school',
            (int) $this->context->userId()
        );
        $this->session->flash('success', match ($decision) {
            'reject' => 'Report rejected. The topic is closed for that student again.',
            'confirm_school' => 'Confirmed and recorded as taught for the whole school.',
            default => 'Confirmed for this student.',
        });

        return Response::redirect('/studio/checks');
    }

    public function report(Request $request): Response
    {
        $schools = $this->schools->allSchools();
        foreach ($schools as &$s) {
            $s['fresh'] = SchoolService::freshnessOf($s['coverage_updated']);
        }
        unset($s);

        return Response::html($this->view->render('studio/honesty', [
            'title' => 'Honesty report',
            'report' => $this->access->honestyReport(),
            'schools' => $schools,
            'names' => Subjects::NAMES,
            'staleDays' => SchoolService::STALE_DAYS,
        ]));
    }
}
