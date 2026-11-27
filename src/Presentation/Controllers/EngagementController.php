<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Jobs\JobRunner;
use SmartCircle\Application\Students\SeatCheckService;
use SmartCircle\Application\Templates\TemplateService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** The admin's seat activity page, message templates and the weekly digest. */
final class EngagementController
{
    public function __construct(
        private readonly SeatCheckService $seats,
        private readonly TemplateService $templates,
        private readonly JobRunner $jobs,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Seat activity ---------- */

    public function seats(Request $request): Response
    {
        return $this->seatsPage([], []);
    }

    public function nudge(Request $request): Response
    {
        $this->seats->nudge((int) $request->param('id'), (int) $this->context->userId());
        $this->session->flash('success', 'Nudge sent: it is in their notifications (and email, if they use it).');

        return Response::redirect('/studio/seats');
    }

    public function pause(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->seats->pause($id, $request->input('until'), $request->input('reason'), (int) $this->context->userId());
        if ($error !== '') {
            return $this->seatsPage([$id => $error], [$id => ['until' => $request->input('until'), 'reason' => $request->input('reason')]], 422);
        }
        $this->session->flash('success', 'Checks paused until ' . date('j F', (int) strtotime($request->input('until'))) . '.');

        return Response::redirect('/studio/seats');
    }

    public function release(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->seats->release($id, $request->input('reason'), (int) $this->context->userId(), $request->ip());
        if ($error !== '') {
            return $this->seatsPage([$id => $error], [$id => ['release' => $request->input('reason')]], 422);
        }
        $this->session->flash('success', 'The seat is free. You can now admit someone from the waiting list in Admissions.');

        return Response::redirect('/studio/seats');
    }

    /* ---------- Templates ---------- */

    public function templates(Request $request): Response
    {
        return Response::html($this->view->render('studio/templates/index', ['title' => 'Message templates', 'templates' => $this->templates->all()]));
    }

    public function template(Request $request): Response
    {
        $t = $this->templateOr404($request);

        return $this->templatePage($t, [], ['subject' => (string) $t['subject'], 'body' => (string) $t['body']]);
    }

    public function saveTemplate(Request $request): Response
    {
        $t = $this->templateOr404($request);
        $old = ['subject' => $request->input('subject'), 'body' => $request->input('body')];
        $errors = $this->templates->save($t['key'], $old['subject'], $old['body'], (int) $this->context->userId());
        if ($errors !== []) {
            return $this->templatePage($t, $errors, $old, 422);
        }
        $this->session->flash('success', 'Saved. New messages use your wording.');

        return Response::redirect('/studio/templates/' . $t['key']);
    }

    public function resetTemplate(Request $request): Response
    {
        $t = $this->templateOr404($request);
        $this->templates->reset($t['key']);
        $this->session->flash('info', 'Back to the default wording.');

        return Response::redirect('/studio/templates/' . $t['key']);
    }

    /* ---------- Digest ---------- */

    public function digestNow(Request $request): Response
    {
        $n = $this->jobs->digestNow();
        $this->session->flash('success', 'Weekly digest sent to ' . $n . ' student' . ($n === 1 ? '' : 's') . ' and to you.');

        return Response::redirect('/studio/mail');
    }

    /** @param array<int, string> $errors @param array<int, array<string, string>> $old */
    private function seatsPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/seats', [
            'title' => 'Seat activity',
            'quiet' => $this->seats->quiet(),
            'errors' => $errors,
            'old' => $old,
            'nudgeAfter' => SeatCheckService::NUDGE_AFTER_DAYS,
            'reviewAfter' => SeatCheckService::REVIEW_AFTER_DAYS,
            'job' => $this->jobs->job('seat_checks'),
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function templateOr404(Request $request): array
    {
        $key = $request->param('key');

        return isset(TemplateService::DEFAULTS[$key]) ? $this->templates->current($key) : throw new HttpException(404);
    }

    /** @param array<string, mixed> $t @param array<string, string> $errors @param array<string, string> $old */
    private function templatePage(array $t, array $errors, array $old, int $status = 200): Response
    {
        $sample = ['guardian' => 'Mayi Phiri', 'tutor' => 'Blessings Emulyn', 'child' => 'Chikondi Banda', 'child_first' => 'Chikondi', 'form' => '2',
            'school_part' => ', Likuni Girls Secondary', 'link' => 'https://smartcircle.gt.tc/g/…', 'call_part' => '', 'month' => date('F'),
            'lessons' => '5', 'work' => '3', 'lessons_text' => '5 lessons', 'work_text' => '3 pieces of work', 'note' => '(a sentence about how active they were)',
            'first' => 'Chikondi', 'letter_part' => "\n\nYour letter: https://smartcircle.gt.tc/letters/ABCD1234", 'status_part' => '',
            'library_part' => "\n\nThe free library is open to everyone: https://smartcircle.gt.tc/library", 'student' => 'Chikondi'];

        return Response::html($this->view->render('studio/templates/edit', [
            'title' => (string) $t['name'],
            't' => $t,
            'errors' => $errors,
            'old' => $old,
            'preview' => ['subject' => TemplateService::fill($old['subject'], $sample), 'body' => TemplateService::fill($old['body'], $sample)],
        ]), $status);
    }
}
