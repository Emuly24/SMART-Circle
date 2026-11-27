<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Grades\GradeService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Grades: students see theirs; the tutor sees the gradebook, adjusts settings and issues term reports. */
final class GradesController
{
    public function __construct(
        private readonly GradeService $grades,
        private readonly CalendarService $calendar,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Students ---------- */

    public function mine(Request $request): Response
    {
        $termId = $this->termId($request);
        $userId = (int) $this->context->userId();

        return Response::html($this->view->render('grades/mine', [
            'title' => 'My grades',
            'terms' => $this->calendar->terms(),
            'termId' => $termId,
            'g' => $termId !== null ? $this->grades->forStudent($userId, $termId) : null,
            'reports' => $this->grades->reportsFor($userId),
            'names' => Subjects::NAMES,
            'categories' => GradeService::CATEGORIES,
            'weights' => $this->grades->weights(),
        ]));
    }

    /* ---------- Tutor ---------- */

    public function book(Request $request): Response
    {
        $termId = $this->termId($request);
        $form = max(1, min(4, (int) $request->query('form', '1')));

        return Response::html($this->view->render('studio/grades/index', [
            'title' => 'Grades',
            'terms' => $this->calendar->terms(),
            'termId' => $termId,
            'form' => $form,
            'rows' => $termId !== null ? $this->grades->gradebook($termId, $form) : [],
            'names' => Subjects::NAMES,
            'subjects' => array_keys(Subjects::NAMES),
        ]));
    }

    public function student(Request $request): Response
    {
        $student = $this->grades->student((int) $request->param('id')) ?? throw new HttpException(404);
        $termId = $this->termId($request);

        return Response::html($this->view->render('studio/grades/student', [
            'title' => (string) $student['full_name'],
            'student' => $student,
            'terms' => $this->calendar->terms(),
            'termId' => $termId,
            'g' => $termId !== null ? $this->grades->termGrades($student, $termId) : null,
            'reports' => $this->grades->reportsFor((int) $student['user_id']),
            'names' => Subjects::NAMES,
            'categories' => GradeService::CATEGORIES,
        ]));
    }

    public function settings(Request $request): Response
    {
        return $this->settingsPage([], null);
    }

    public function saveSettings(Request $request): Response
    {
        $all = $request->allInput();
        $weights = is_array($all['weights'] ?? null) ? $all['weights'] : [];
        $bands = is_array($all['bands'] ?? null) ? $all['bands'] : [];
        $errors = $this->grades->saveSettings($weights, $bands);
        if ($errors !== []) {
            return $this->settingsPage($errors, ['weights' => $weights, 'bands' => $bands], 422);
        }
        $this->session->flash('success', 'Grade settings saved. Grades are recalculated with them.');

        return Response::redirect('/studio/grades/settings');
    }

    public function issue(Request $request): Response
    {
        $termId = (int) $request->input('term');
        $form = (int) $request->input('form');
        $all = $request->allInput();
        $n = $this->grades->issueReports($termId, $form, is_array($all['comment'] ?? null) ? $all['comment'] : [], (int) $this->context->userId());
        $this->session->flash('success', "Term reports issued for $n student" . ($n === 1 ? '' : 's') . '. Each student has been told.');

        return Response::redirect('/studio/grades?term=' . $termId . '&form=' . $form);
    }

    /** @param array<string, string> $errors @param array<string, mixed>|null $old */
    private function settingsPage(array $errors, ?array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/grades/settings', [
            'title' => 'Grade settings',
            'weights' => $this->grades->weights(),
            'bands' => $this->grades->bands(),
            'categories' => GradeService::CATEGORIES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    private function termId(Request $request): ?int
    {
        $asked = (int) $request->query('term', '0');
        foreach ($this->calendar->terms() as $t) {
            if ((int) $t['id'] === $asked) {
                return $asked;
            }
        }

        return $this->calendar->currentTermId();
    }
}
