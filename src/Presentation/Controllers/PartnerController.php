<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Partners\ContributionCertificateService;
use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Application\Partners\SchoolService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Malawi;
use SmartCircle\Support\Subjects;

/** Tutor: invite school partners, hand out setup codes, see schools. */
final class PartnerController
{
    private const SHOWN_CODE = 'partner_code';

    public function __construct(
        private readonly PartnerService $partners,
        private readonly SchoolService $schools,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?ContributionCertificateService $certificates = null,
    ) {
    }

    public function certificate(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->partners->find($id) ?? throw new HttpException(404);
        $r = $this->certificates?->issue($id, (int) $this->context->userId()) ?? ['code' => null, 'error' => 'Not available.'];
        $this->session->flash(...($r['error'] === '' ? ['success', 'Certificate issued. The teacher has been told; it is also listed below.'] : ['error', $r['error']]));

        return Response::redirect('/studio/partners/' . $id . '#roles');
    }

    public function index(Request $request): Response
    {
        return $this->indexPage([], []);
    }

    public function invite(Request $request): Response
    {
        $input = [
            'full_name' => $request->input('full_name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'school_name' => $request->input('school_name'),
            'district' => $request->input('district'),
            'subjects' => $request->inputList('subjects'),
        ];
        $result = $this->partners->invite($input, (int) $this->context->userId(), $request->ip());
        if ($result['errors'] !== []) {
            return $this->indexPage($result['errors'], $input, 422);
        }
        $this->session->set(self::SHOWN_CODE, ['id' => $result['user_id'], 'code' => $result['code']]);
        $this->session->flash('success', 'Partner account created. Give the teacher the setup code and the invitation letter.');

        return Response::redirect('/studio/partners/' . $result['user_id']);
    }

    public function show(Request $request): Response
    {
        $partner = $this->partners->find((int) $request->param('id')) ?? throw new HttpException(404);
        $shown = $this->session->get(self::SHOWN_CODE);
        $code = is_array($shown) && (int) $shown['id'] === (int) $partner['id'] ? (string) $shown['code'] : null;
        $schoolId = (int) $partner['school_id'];

        return Response::html($this->view->render('studio/partners/show', [
            'title' => (string) $partner['full_name'],
            'partner' => $partner,
            'code' => $code,
            'letters' => $this->partners->letters((int) $partner['id']),
            'progress' => $schoolId > 0 ? $this->schools->progress($schoolId) : [],
            'names' => Subjects::NAMES,
            'contribution' => $this->certificates?->counts((int) $partner['id']) ?? [],
        ]));
    }

    public function hideCode(Request $request): Response
    {
        $this->session->forget(self::SHOWN_CODE);

        return Response::redirect('/studio/partners/' . (int) $request->param('id'));
    }

    public function newCode(Request $request): Response
    {
        $id = (int) $request->param('id');
        $code = $this->partners->issueCode($id, (int) $this->context->userId()) ?? throw new HttpException(404);
        $this->session->set(self::SHOWN_CODE, ['id' => $id, 'code' => $code]);
        $this->session->flash('info', 'New setup code made. Older codes no longer work. The teacher uses it on “Set up my account” (also for a forgotten password).');

        return Response::redirect('/studio/partners/' . $id);
    }

    public function setStatus(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->partners->find($id) ?? throw new HttpException(404);
        $active = $request->input('status') === 'active';
        $this->partners->setActive($id, $active, (int) $this->context->userId(), $request->ip());
        $this->session->flash('info', $active ? 'Account enabled.' : 'Account disabled. The teacher can no longer log in.');

        return Response::redirect('/studio/partners/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function indexPage(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/partners/index', [
            'title' => 'School partners',
            'partners' => $this->partners->all(),
            'schools' => $this->schools->allSchools(),
            'districts' => Malawi::DISTRICTS,
            'names' => Subjects::NAMES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }
}
