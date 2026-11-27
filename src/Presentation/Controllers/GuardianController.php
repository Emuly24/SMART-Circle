<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Guardians\GuardianMessages;
use SmartCircle\Application\Guardians\GuardianService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;

/** Guardians: their confirmation link (no login), and the admin's guardian tools. */
final class GuardianController
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- The guardian's link ---------- */

    public function link(Request $request): Response
    {
        return $this->linkPage($request->param('token'), $request->query('lang'), null);
    }

    public function answer(Request $request): Response
    {
        $token = $request->param('token');
        $knows = $request->input('answer') === 'yes';
        if (!$this->guardians->answer($token, $knows)) {
            throw new HttpException(404);
        }

        return $this->linkPage($token, $request->input('lang'), $knows ? 'yes' : 'no');
    }

    private function linkPage(string $token, string $lang, ?string $answered): Response
    {
        $g = $this->guardians->byToken($token) ?? throw new HttpException(404);
        $lang = isset(GuardianMessages::LANGUAGES[$lang]) ? $lang : (isset(GuardianMessages::LANGUAGES[$g['lang']]) ? $g['lang'] : 'en');

        return Response::html($this->view->render('guardians/confirm', [
            'title' => 'SMART Circle',
            'g' => $g,
            'lang' => $lang,
            'w' => GuardianMessages::confirmPage($lang, $g['first']),
            'token' => $token,
            'answered' => $answered,
        ], 'layouts/minimal'));
    }

    /* ---------- The admin ---------- */

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('studio/guardians/index', [
            'title' => 'Guardians',
            'rows' => $this->guardians->all(),
            'languages' => GuardianMessages::LANGUAGES,
        ]));
    }

    public function show(Request $request): Response
    {
        return $this->page((int) $request->param('id'), [], []);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $in = [];
        foreach (['guardian_name', 'guardian_relationship', 'guardian_phone', 'guardian_whatsapp', 'guardian_email', 'guardian_language'] as $f) {
            $in[$f] = $request->input($f);
        }
        $errors = $this->guardians->update($id, $in);
        if ($errors !== []) {
            return $this->page($id, $errors, $in, 422);
        }
        $this->session->flash('success', 'Guardian details saved.');

        return Response::redirect('/studio/guardians/' . $id);
    }

    public function confirm(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->guardians->confirmByCall($id, (int) $this->context->userId());
        $this->session->flash('success', 'Recorded: the guardian confirmed on a call.');

        return Response::redirect('/studio/guardians/' . $id);
    }

    public function sent(Request $request): Response
    {
        $id = (int) $request->param('id');
        $what = $request->input('what') === 'monthly' ? 'monthly' : 'admission';
        $this->guardians->logSent($id, $what, $request->input('channel'), (int) $this->context->userId());
        $this->session->flash('success', 'Saved to the contact log.');

        return Response::redirect($request->input('back') === 'list' ? '/studio/guardians' : '/studio/guardians/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function page(int $id, array $errors, array $old, int $status = 200): Response
    {
        $kit = $this->guardians->kit($id, (string) ($this->context->user()['full_name'] ?? 'the SMART Circle tutor')) ?? throw new HttpException(404);

        return Response::html($this->view->render('studio/guardians/show', [
            'title' => 'Guardian · ' . $kit['g']['full_name'],
            'k' => $kit,
            'languages' => GuardianMessages::LANGUAGES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }
}
