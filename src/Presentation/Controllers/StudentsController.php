<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Students\StudentOverviewService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** The tutor's student overview: activity, early-warning flags, contact buttons and the contact log. */
final class StudentsController
{
    public function __construct(
        private readonly StudentOverviewService $students,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $all = $this->students->all();
        $filter = $request->query('show');
        $list = $filter === 'flagged' ? array_values(array_filter($all, static fn (array $s): bool => $s['flags'] !== [])) : $all;

        return Response::html($this->view->render('studio/students/index', [
            'title' => 'Students',
            'students' => $list,
            'total' => count($all),
            'flagged' => count(array_filter($all, static fn (array $s): bool => $s['flags'] !== [])),
            'filter' => $filter,
        ]));
    }

    public function show(Request $request): Response
    {
        return $this->page((int) $request->param('id'), [], []);
    }

    public function logContact(Request $request): Response
    {
        $id = (int) $request->param('id');
        $in = ['who' => $request->input('who'), 'channel' => $request->input('channel'), 'summary' => $request->input('summary')];
        $errors = $this->students->logContact($id, $in['who'], $in['channel'], $in['summary'], (int) $this->context->userId());
        if ($errors !== []) {
            return $this->page($id, $errors, $in, 422);
        }
        $this->session->flash('success', 'Saved to the contact log.');

        return Response::redirect('/studio/students/' . $id . '#contact-log');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function page(int $id, array $errors, array $old, int $status = 200): Response
    {
        $s = $this->students->one($id);
        if ($s === null) {
            throw new HttpException(404);
        }

        return Response::html($this->view->render('studio/students/show', [
            'title' => (string) $s['full_name'],
            's' => $s,
            'channels' => StudentOverviewService::CHANNELS,
            'names' => Subjects::NAMES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }
}
