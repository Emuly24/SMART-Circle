<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Partners\ContributionService;
use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Partner teachers' contributions (calendar check, resources, topic suggestions) and the admin's review. */
final class ContributionController
{
    public function __construct(
        private readonly ContributionService $contributions,
        private readonly PartnerService $partners,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Partner teachers ---------- */

    public function page(Request $request): Response
    {
        return $this->partnerPage([], []);
    }

    public function calendar(Request $request): Response
    {
        $p = $this->partner();
        $error = $this->contributions->answerCalendar((int) $this->context->userId(), (int) $p['school_id'], (string) $p['school_name'],
            $request->input('answer') === 'correct', $request->input('note'));
        if ($error !== null) {
            return $this->partnerPage(['calendar' => $error], ['note' => $request->input('note')], 422);
        }
        $this->session->flash('success', $request->input('answer') === 'correct' ? 'Thank you for confirming the calendar.' : 'Thank you. The tutor has been told and will correct it.');

        return Response::redirect('/partner/contribute#calendar');
    }

    public function share(Request $request): Response
    {
        $p = $this->partner();
        $in = ['kind' => $request->input('kind'), 'title' => $request->input('title'), 'subject' => $request->input('subject'),
            'form' => $request->input('form'), 'note' => $request->input('note')];
        $via = $request->input('via');
        $r = $this->contributions->share((int) $this->context->userId(), (int) $p['school_id'], (string) $p['full_name'], (string) $p['school_name'],
            $in, $via, $request->file('file'));
        if ($r['errors'] !== []) {
            return $this->partnerPage($r['errors'], $in, 422);
        }
        if ($r['whatsapp'] !== null) {
            // Recorded here; WhatsApp opens with the message ready, and the teacher attaches the file there.
            return Response::redirect($r['whatsapp']);
        }
        $this->session->flash('success', 'Uploaded. Thank you for sharing it.');

        return Response::redirect('/partner/contribute#resources');
    }

    public function suggest(Request $request): Response
    {
        $p = $this->partner();
        $in = ['form' => $request->input('form'), 'subject' => $request->input('subject'), 'parent_id' => $request->input('parent_id'),
            'title' => $request->input('title'), 'note' => $request->input('note')];
        $errors = $this->contributions->suggest((int) $this->context->userId(), (int) $p['school_id'], (string) $p['school_name'], $p['subject_list'], $in);
        if ($errors !== []) {
            return $this->partnerPage(array_combine(array_map(static fn (string $k): string => 's_' . $k, array_keys($errors)), $errors), ['s' => $in], 422);
        }
        $this->session->flash('success', 'Thank you. The tutor will look at your suggestion.');

        return Response::redirect('/partner/contribute#suggest');
    }

    public function partnerFile(Request $request): Response
    {
        $f = $this->contributions->file((int) $request->param('id'), (int) $this->partner()['school_id']) ?? throw new HttpException(404);

        return Response::files([$f['path']], $f['mime'], (int) filesize($f['path']), $f['name']);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function partnerPage(array $errors, array $old, int $status = 200): Response
    {
        $p = $this->partner();
        $schoolId = (int) $p['school_id'];

        return Response::html($this->view->render('partner/contribute', [
            'title' => 'Help keep SMART Circle accurate',
            'p' => $p,
            'calendar' => $this->contributions->calendarStatus($schoolId),
            'resources' => $this->contributions->resources($schoolId),
            'suggestions' => $this->contributions->suggestions($schoolId),
            'kinds' => ContributionService::KINDS,
            'names' => array_intersect_key(Subjects::NAMES, array_flip($p['subject_list'] ?: array_keys(Subjects::NAMES))),
            'mainTopics' => array_values(array_filter($this->contributions->mainTopics(), static fn (array $o): bool => in_array($o['subject'], $p['subject_list'] ?: array_keys(Subjects::NAMES), true))),
            'canWhatsapp' => $this->contributions->whatsappLink('x', 'y', ['kind' => 'other', 'title' => 'x', 'subject' => null, 'form' => null]) !== null,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function partner(): array
    {
        $p = $this->partners->find((int) $this->context->userId());
        if ($p === null || empty($p['school_id'])) {
            throw new HttpException(403, 'This page is for school partners.');
        }

        return $p;
    }

    /* ---------- The admin ---------- */

    public function review(Request $request): Response
    {
        return Response::html($this->view->render('studio/contributions', [
            'title' => 'From partner teachers',
            'calendar' => $this->contributions->calendarOverview(),
            'resources' => $this->contributions->resources(null),
            'suggestions' => $this->contributions->suggestions(null),
            'kinds' => ContributionService::KINDS,
            'names' => Subjects::NAMES,
        ]));
    }

    public function reviewed(Request $request): Response
    {
        $this->contributions->review((int) $request->param('id'), (int) $this->context->userId());

        return Response::redirect('/studio/contributions#resources');
    }

    public function adminFile(Request $request): Response
    {
        $f = $this->contributions->file((int) $request->param('id'), null) ?? throw new HttpException(404);

        return Response::files([$f['path']], $f['mime'], (int) filesize($f['path']), $f['name']);
    }

    public function decide(Request $request): Response
    {
        $error = $this->contributions->decide((int) $request->param('id'), $request->input('decision') === 'add', $request->input('title'),
            $request->input('note'), (int) $this->context->userId());
        $this->session->flash(...($error === null ? ['success', $request->input('decision') === 'add' ? 'Added to the topic catalogue.' : 'Declined; the teacher has been told.'] : ['error', $error]));

        return Response::redirect('/studio/contributions#suggestions');
    }

    public function resolve(Request $request): Response
    {
        $this->contributions->resolveCalendar((int) $request->param('id'));
        $this->session->flash('success', 'Marked as corrected.');

        return Response::redirect('/studio/contributions#calendar');
    }
}
