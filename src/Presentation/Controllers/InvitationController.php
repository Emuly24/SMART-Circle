<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Partners\InvitationService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** The tutor writes invitation letters to school teachers and volunteer tutors. */
final class InvitationController
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly Context $context,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        $type = $request->query('type');

        return $this->page([], ['type' => isset(InvitationService::TYPES[$type]) ? $type : 'teacher_invitation', 'subjects' => []]);
    }

    public function issue(Request $request): Response
    {
        $in = ['type' => $request->input('type'), 'title' => $request->input('title'), 'full_name' => $request->input('full_name'),
            'school' => $request->input('school'), 'district' => $request->input('district'), 'note' => $request->input('note'),
            'subjects' => $request->inputList('subjects')];
        $r = $this->invitations->issue($in, (int) $this->context->userId());
        if ($r['errors'] !== []) {
            return $this->page($r['errors'], $in, 422);
        }

        return Response::redirect('/letters/' . $r['code']);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function page(array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/invitations', [
            'title' => 'Invitation letters',
            'errors' => $errors,
            'old' => $old,
            'types' => InvitationService::TYPES,
            'titles' => InvitationService::TITLES,
            'names' => Subjects::NAMES,
            'issued' => $this->invitations->issued(),
        ]), $status);
    }
}
