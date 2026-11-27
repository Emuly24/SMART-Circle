<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Admissions\DocumentService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Printable letters and the public verification page. */
final class DocumentController
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly string $siteUrl,
    ) {
    }

    public function letter(Request $request): Response
    {
        $doc = $this->documents->find($request->param('code')) ?? throw new HttpException(404);
        $data = $doc['data'];

        // The tutor, or the applicant who has just checked their status, may open the letter.
        $isTutor = $this->context->role() === 'tutor';
        $isApplicant = isset($data['reference']) && $this->session->get('apply_ref') === $data['reference'];
        $isOwner = $doc['subject_type'] === 'user' && (int) $doc['subject_id'] === $this->context->userId();
        if (!$isTutor && !$isApplicant && !$isOwner) {
            throw new HttpException(404);
        }

        return Response::html($this->view->render('letters/letter', [
            'title' => $doc['title'],
            'doc' => $doc,
            'data' => $data,
            // Admission letters list subjects as "a,b,c"; term reports carry a table of grades instead.
            'subjects' => Subjects::names(array_filter(explode(',', is_string($data['help_subjects'] ?? $data['subjects'] ?? null) ? (string) ($data['help_subjects'] ?? $data['subjects']) : ''))),
            'verifyUrl' => rtrim($this->siteUrl, '/') . '/verify/' . $doc['code'],
            'libraryUrl' => rtrim($this->siteUrl, '/') . '/library',
        ], 'layouts/print'));
    }

    public function verifyForm(Request $request): Response
    {
        $code = trim($request->query('code'));
        if ($code !== '') {
            return Response::redirect('/verify/' . rawurlencode($code));
        }

        return Response::html($this->view->render('verify/form', ['title' => 'Verify a document']));
    }

    public function verify(Request $request): Response
    {
        $doc = $this->documents->find($request->param('code'));

        return Response::html($this->view->render('verify/result', [
            'title' => 'Verify a document',
            'doc' => $doc,
            'code' => $request->param('code'),
        ]), $doc === null ? 404 : 200);
    }
}
