<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Partners\PartnerService;
use SmartCircle\Application\Partners\SchoolService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/**
 * A school's coverage and textbooks. Partners work on their own school under /partner;
 * the tutor works on any school under /studio/schools/{school}.
 */
final class SchoolController
{
    public function __construct(
        private readonly SchoolService $schools,
        private readonly PartnerService $partners,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /** Tutor: one school's summary. */
    public function summary(Request $request): Response
    {
        $s = $this->scope($request);

        return Response::html($this->view->render('schools/summary', [
            'title' => (string) $s['school']['name'],
            'scope' => $s,
            'progress' => $this->schools->progress($s['id']),
            'students' => $this->schools->studentsByForm($s['id']),
            'top' => $this->schools->topRequests($s['id'], $s['subjects']),
            'books' => $this->schools->textbooks($s['id']),
            'fresh' => $this->schools->freshness($s['id']),
            'names' => Subjects::NAMES,
        ]));
    }

    public function coverage(Request $request): Response
    {
        $s = $this->scope($request);
        [$form, $subject] = $this->formSubject($request->query('form', '1'), $request->query('subject'), $s['subjects']);

        return Response::html($this->view->render('schools/coverage', [
            'title' => 'Topics taught',
            'scope' => $s,
            'form' => $form,
            'subject' => $subject,
            'tree' => $this->schools->coverageTree($s['id'], $form, $subject),
            'statuses' => SchoolService::COVERAGE,
            'names' => Subjects::NAMES,
        ]));
    }

    public function saveCoverage(Request $request): Response
    {
        $s = $this->scope($request);
        [$form, $subject] = $this->formSubject($request->input('form'), $request->input('subject'), $s['subjects']);
        $posted = $request->allInput()['status'] ?? [];
        $n = $this->schools->saveCoverage($s['id'], $form, $subject, is_array($posted) ? $posted : [], (int) $this->context->userId(), $s['isTutor'] ? 'tutor' : 'partner');
        $this->session->flash('success', "Saved. Thank you: $n topics marked for Form $form " . Subjects::NAMES[$subject] . '.');

        return Response::redirect($s['base'] . '/coverage?form=' . $form . '&subject=' . $subject);
    }

    /** "Nothing has changed since my last report" (keeps the report fresh). */
    public function markChecked(Request $request): Response
    {
        $s = $this->scope($request);
        $this->schools->markChecked($s['id']);
        $this->session->flash('success', 'Thank you. Your report is up to date.');

        return Response::redirect($s['isTutor'] ? $s['base'] : '/dashboard');
    }

    public function textbooks(Request $request): Response
    {
        return $this->textbooksPage($this->scope($request), [], []);
    }

    public function addTextbook(Request $request): Response
    {
        $s = $this->scope($request);
        $input = [];
        foreach (['known_id', 'subject', 'form', 'title', 'edition', 'publisher'] as $f) {
            $input[$f] = $request->input($f);
        }
        if ((int) $input['known_id'] === 0 && $input['title'] === '') {
            return $this->textbooksPage($s, ['known_id' => 'Choose a book from the list, or type a new one.'], $input, 422);
        }
        $errors = $this->schools->addTextbook($s['id'], $input);
        if ($errors !== []) {
            return $this->textbooksPage($s, $errors, $input, 422);
        }
        $this->session->flash('success', 'Textbook added. Now add the chapter and pages for each topic.');

        return Response::redirect($s['base'] . '/textbooks');
    }

    public function removeTextbook(Request $request): Response
    {
        $s = $this->scope($request);
        $this->schools->removeTextbook($s['id'], (int) $request->param('book'));
        $this->session->flash('info', 'Textbook removed from this school’s list.');

        return Response::redirect($s['base'] . '/textbooks');
    }

    public function refs(Request $request): Response
    {
        $s = $this->scope($request);
        $book = $this->bookOr404($s, $request);

        return Response::html($this->view->render('schools/refs', [
            'title' => (string) $book['title'],
            'scope' => $s,
            'book' => $book,
            'tree' => $this->schools->refsTree($book),
            'names' => Subjects::NAMES,
        ]));
    }

    public function saveRefs(Request $request): Response
    {
        $s = $this->scope($request);
        $book = $this->bookOr404($s, $request);
        $all = $request->allInput();
        $n = $this->schools->saveRefs($book, is_array($all['chapter'] ?? null) ? $all['chapter'] : [], is_array($all['pages'] ?? null) ? $all['pages'] : []);
        $this->session->flash('success', "Saved. $n topics now point students to this book.");

        return Response::redirect($s['base'] . '/textbooks/' . $book['id']);
    }

    /** @param array<string, mixed> $s @param array<string, string> $errors @param array<string, string> $old */
    private function textbooksPage(array $s, array $errors, array $old, int $status = 200): Response
    {
        return Response::html($this->view->render('schools/textbooks', [
            'title' => 'Textbooks',
            'scope' => $s,
            'books' => $this->schools->textbooks($s['id']),
            'known' => $this->schools->knownTextbooks(),
            'names' => Subjects::NAMES,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /**
     * Which school, which subjects, and the URL prefix, depending on who is asking.
     * @return array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool}
     */
    private function scope(Request $request): array
    {
        if ($this->context->role() === 'tutor') {
            $id = (int) $request->param('school');
            $school = $this->schools->school($id) ?? throw new HttpException(404);

            return ['id' => $id, 'school' => $school, 'subjects' => array_keys(Subjects::NAMES), 'base' => '/studio/schools/' . $id, 'isTutor' => true];
        }
        $partner = $this->partners->find((int) $this->context->userId());
        if ($partner === null || empty($partner['school_id'])) {
            throw new HttpException(403, 'This page is for school partners.');
        }
        $school = $this->schools->school((int) $partner['school_id']) ?? throw new HttpException(403);

        return ['id' => (int) $partner['school_id'], 'school' => $school, 'subjects' => $partner['subject_list'], 'base' => '/partner', 'isTutor' => false];
    }

    /** @param list<string> $allowed @return array{0: int, 1: string} */
    private function formSubject(string $form, string $subject, array $allowed): array
    {
        return [max(1, min(4, (int) $form)), in_array($subject, $allowed, true) ? $subject : $allowed[0]];
    }

    /** @param array<string, mixed> $s @return array<string, mixed> */
    private function bookOr404(array $s, Request $request): array
    {
        return $this->schools->schoolTextbook($s['id'], (int) $request->param('book')) ?? throw new HttpException(404);
    }
}
