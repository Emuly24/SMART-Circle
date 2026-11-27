<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\OfficeHours\OfficeHoursService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\PhoneNumber;

/** Office hours: booking (students, alumni) and running them (tutor). */
final class OfficeHoursController
{
    public function __construct(
        private readonly OfficeHoursService $hours,
        private readonly \Closure $studentForm,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('office_hours/index', [
            'title' => 'Office hours',
            'slots' => $this->hours->forPerson($this->uid(), $this->role(), $this->form()),
            'modes' => OfficeHoursService::MODES,
        ]));
    }

    public function book(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->hours->book($id, $this->uid(), $this->role(), $this->form(), $request->input('question'));
        $this->session->flash(...($error === null ? ['success', 'Booked. The tutor will contact you at that time.'] : ['error', $error]));

        return Response::redirect('/office-hours#slot-' . $id);
    }

    public function cancel(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->hours->cancelBooking($id, $this->uid());
        $this->session->flash(...($error === null ? ['info', 'Your booking is cancelled.'] : ['error', $error]));

        return Response::redirect('/office-hours#slot-' . $id);
    }

    public function studio(Request $request): Response
    {
        return $this->studioPage([], []);
    }

    public function create(Request $request): Response
    {
        $in = ['date' => $request->input('date'), 'start' => $request->input('start'), 'end' => $request->input('end'), 'mode' => $request->input('mode'),
            'capacity' => $request->input('capacity'), 'audience' => $request->input('audience'), 'note' => $request->input('note')];
        $errors = $this->hours->create($in, $this->uid());
        if ($errors !== []) {
            return $this->studioPage($errors, $in, 422);
        }
        $this->session->flash('success', 'Office hours added; students who can book were notified.');

        return Response::redirect('/studio/office-hours');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function studioPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/office_hours/index', [
            'title' => 'Office hours',
            'slots' => $this->hours->forTutor(),
            'modes' => OfficeHoursService::MODES,
            'audiences' => OfficeHoursService::AUDIENCES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    public function slot(Request $request): Response
    {
        $s = $this->hours->slot((int) $request->param('id')) ?? throw new HttpException(404);
        foreach ($s['bookings'] as &$b) {
            $phone = PhoneNumber::normalize((string) $b['phone']);
            $b['tel'] = $phone !== null ? 'tel:' . $phone : null;
            $b['wa'] = $phone !== null ? 'https://wa.me/' . ltrim($phone, '+') : null;
        }
        unset($b);

        return Response::html($this->view->render('studio/office_hours/show', [
            'title' => 'Office hours',
            's' => $s,
            'modes' => OfficeHoursService::MODES,
            'audiences' => OfficeHoursService::AUDIENCES,
        ]));
    }

    public function cancelSlot(Request $request): Response
    {
        $ok = $this->hours->cancelSlot((int) $request->param('id'));
        $this->session->flash(...($ok ? ['info', 'Session cancelled; anyone booked was told.'] : ['error', 'That session cannot be cancelled.']));

        return Response::redirect('/studio/office-hours');
    }

    public function markBooking(Request $request): Response
    {
        $slotId = $this->hours->markBooking((int) $request->param('id'), $request->input('status'));
        if ($slotId === null) {
            throw new HttpException(404);
        }

        return Response::redirect('/studio/office-hours/' . $slotId);
    }

    private function form(): ?int
    {
        return $this->role() === 'student' ? ($this->studentForm)($this->uid()) : null;
    }

    private function uid(): int
    {
        return (int) $this->context->userId();
    }

    private function role(): string
    {
        return (string) $this->context->role();
    }
}
