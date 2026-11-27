<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Team\TeamAgreement;
use SmartCircle\Application\Team\TeamService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** The SMART Circle Team: the admin's team page, and the agreement new team tutors sign. */
final class TeamController
{
    public function __construct(
        private readonly TeamService $team,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->page([], []);
    }

    public function invite(Request $request): Response
    {
        $in = ['full_name' => $request->input('full_name'), 'phone' => $request->input('phone'), 'email' => $request->input('email'),
            'subjects' => $request->inputList('subjects')];
        $r = $this->team->invite($in, $this->uid(), $request->ip());
        if ($r['errors'] !== []) {
            return $this->page($r['errors'], $in, 422);
        }
        $this->session->set('team_code_' . $r['user_id'], $r['code']);
        $this->session->flash('success', 'Invitation ready. Send them the setup code below.');

        return Response::redirect('/studio/team/' . $r['user_id']);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function page(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/team/index', [
            'title' => 'The SMART Circle Team',
            'members' => $this->team->all(),
            'names' => Subjects::NAMES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $t = $this->team->find($id);
        if ($t === null || (bool) $t['is_practice']) {
            throw new HttpException(404);
        }

        return Response::html($this->view->render('studio/team/show', [
            'title' => (string) $t['full_name'],
            't' => $t,
            'code' => $this->session->get('team_code_' . $id),
            'names' => Subjects::NAMES,
        ]));
    }

    public function newCode(Request $request): Response
    {
        $id = (int) $request->param('id');
        $code = $this->team->newCodeFor($id, $this->uid()) ?? throw new HttpException(404);
        $this->session->set('team_code_' . $id, $code);

        return Response::redirect('/studio/team/' . $id);
    }

    public function hideCode(Request $request): Response
    {
        $this->session->forget('team_code_' . (int) $request->param('id'));

        return Response::redirect('/studio/team/' . (int) $request->param('id'));
    }

    public function subjects(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $this->team->setSubjects($id, $request->inputList('subjects'), $this->uid(), $request->ip());
        $this->session->flash(...($error === null ? ['success', 'Subjects saved.'] : ['error', $error]));

        return Response::redirect('/studio/team/' . $id);
    }

    public function status(Request $request): Response
    {
        $id = (int) $request->param('id');
        $active = $request->input('active') === '1';
        $this->team->setActive($id, $active, $this->uid(), $request->ip());
        $this->session->flash('info', $active ? 'They can sign in again.' : 'Removed from the team. They can no longer sign in; their work is kept.');

        return Response::redirect('/studio/team/' . $id);
    }

    /* ---------- The agreement (team tutors) ---------- */

    public function agreement(Request $request): Response
    {
        return $this->agreementPage(null);
    }

    public function agree(Request $request): Response
    {
        $agreed = $request->input('decision') === 'agree';
        $error = $this->team->agree($this->uid(), $agreed, $request->input('signed_name'), $request->ip());
        if ($error !== null) {
            return $this->agreementPage($error, 422);
        }
        if (!$agreed) {
            $this->session->logout();

            return Response::html($this->view->render('team/declined', ['title' => 'Thank you']));
        }
        $this->session->flash('success', 'Welcome to the SMART Circle Team!');

        return Response::redirect('/dashboard');
    }

    private function agreementPage(?string $error, int $status = 200): Response
    {
        $t = $this->team->find($this->uid()) ?? throw new HttpException(404);
        if ($t['status'] === 'active') {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->view->render('team/agreement', [
            'title' => 'Joining the SMART Circle Team',
            't' => $t,
            'names' => Subjects::NAMES,
            'philosophy' => TeamAgreement::PHILOSOPHY,
            'principles' => TeamAgreement::PRINCIPLES,
            'duties' => TeamAgreement::DUTIES,
            'error' => $error,
        ]), $status);
    }

    private function uid(): int
    {
        return (int) $this->context->userId();
    }
}
