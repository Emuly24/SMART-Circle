<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** The academic calendar: the tutor keeps the national dates; partner schools add their own. */
final class CalendarController
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly PartnerService $partners,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->page([], []);
    }

    public function saveYear(Request $request): Response
    {
        if (!$this->isTutor()) {
            throw new HttpException(403);
        }
        $in = ['name' => $request->input('name')];
        foreach ([1, 2, 3] as $n) {
            $in["t{$n}_open"] = $request->input("t{$n}_open");
            $in["t{$n}_close"] = $request->input("t{$n}_close");
        }
        $errors = $this->calendar->saveYear($in);
        if ($errors !== []) {
            return $this->page($errors, $in, 422);
        }
        $this->session->flash('success', 'Academic year ' . $in['name'] . ' saved.');

        return Response::redirect('/studio/calendar');
    }

    public function addEvent(Request $request): Response
    {
        $schoolId = $this->schoolId();
        $in = ['kind' => $request->input('kind'), 'title' => $request->input('title'), 'starts_on' => $request->input('starts_on'),
            'ends_on' => $request->input('ends_on'), 'forms' => $request->inputList('forms')];
        $errors = $this->calendar->addEvent($schoolId, $in, (int) $this->context->userId());
        if ($errors !== []) {
            return $this->page($errors, $in, 422);
        }
        $this->session->flash('success', 'Date added to the calendar.');

        return Response::redirect($this->base());
    }

    public function deleteEvent(Request $request): Response
    {
        $ok = $this->calendar->deleteEvent((int) $request->param('id'), $this->schoolId());
        $this->session->flash(...($ok ? ['info', 'Date removed.'] : ['error', 'You can only remove your own school’s dates.']));

        return Response::redirect($this->base());
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function page(array $errors, array $old, int $status = 200): Response
    {
        $schoolId = $this->schoolId();

        return Response::html($this->view->render('calendar/index', [
            'title' => 'Academic calendar',
            'isTutor' => $this->isTutor(),
            'base' => $this->base(),
            'terms' => $this->calendar->terms(),
            'today' => $this->calendar->today(),
            'events' => $this->calendar->events($schoolId, $this->isTutor()),
            'kinds' => $this->isTutor() ? CalendarService::EVENT_KINDS : array_intersect_key(CalendarService::EVENT_KINDS, array_flip(CalendarService::SCHOOL_KINDS)),
            'allKinds' => CalendarService::EVENT_KINDS,
            'schoolId' => $schoolId,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    private function isTutor(): bool
    {
        return $this->context->role() === 'tutor';
    }

    private function base(): string
    {
        return $this->isTutor() ? '/studio/calendar' : '/partner/calendar';
    }

    /** NULL for the tutor (national dates); the partner's own school otherwise. */
    private function schoolId(): ?int
    {
        if ($this->isTutor()) {
            return null;
        }
        $p = $this->partners->find((int) $this->context->userId());
        if ($p === null || empty($p['school_id'])) {
            throw new HttpException(403, 'This page is for school partners.');
        }

        return (int) $p['school_id'];
    }
}
