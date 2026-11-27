<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Assessments\Marker;
use SmartCircle\Application\Partners\GuestTeacherService;
use SmartCircle\Application\Partners\QuestionSuggestionService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Question Contributor and Guest Teacher: the teacher's pages and the tutor's decisions. */
final class TeacherRolesController
{
    private const QUESTION_FIELDS = ['form', 'subject', 'topic_id', 'type', 'prompt', 'marks', 'correct', 'answer', 'unit', 'tolerance_pct', 'unit_required', 'accepted', 'model_answer', 'is_application'];

    public function __construct(
        private readonly QuestionSuggestionService $suggestions,
        private readonly GuestTeacherService $guests,
        private readonly TopicService $topics,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /* ---------- Partner teachers: suggest questions ---------- */

    public function questions(Request $request): Response
    {
        $subjects = $this->suggestions->subjectsOf((int) $this->context->userId());

        return $this->questionsPage(['id' => null, 'form' => 1, 'subject' => $subjects[0] ?? 'mathematics', 'type' => 'mcq', 'marks' => 1, 'data' => []], []);
    }

    public function suggest(Request $request): Response
    {
        $in = [];
        foreach (self::QUESTION_FIELDS as $f) {
            $in[$f] = $request->input($f);
        }
        $in['options'] = $request->inputList('options');
        $r = $this->suggestions->suggest((int) $this->context->userId(), $in);
        if ($r['errors'] !== []) {
            return $this->questionsPage(['id' => null] + $r['clean'], $r['errors'], 422);
        }
        $this->session->flash('success', 'Thank you! The tutor will look at your question.');

        return Response::redirect('/partner/questions');
    }

    /* ---------- The tutor ---------- */

    public function review(Request $request): Response
    {
        return Response::html($this->view->render('studio/question_suggestions', [
            'title' => 'Suggested questions',
            'names' => Subjects::NAMES,
            'types' => Marker::TYPES,
        ] + $this->suggestions->forTutor()));
    }

    public function decide(Request $request): Response
    {
        $id = (int) $request->param('id');
        $error = $request->input('decision') === 'accept' ? $this->suggestions->accept($id) : $this->suggestions->decline($id, $request->input('note'));
        $this->session->flash(...($error === '' ? ['success', $request->input('decision') === 'accept' ? 'Added to the question bank, with the teacher\'s name.' : 'Declined. The teacher has your reason.'] : ['error', $error]));

        return Response::redirect('/studio/question-suggestions');
    }

    public function setGuest(Request $request): Response
    {
        $id = (int) $request->param('id');
        $on = $request->input('guest') === '1';
        $this->guests->setGuest($id, $on);
        $this->session->flash('success', $on ? 'They can now record lessons in their subjects. You publish each one.' : 'They can no longer record lessons. Their lessons stay.');

        return Response::redirect('/studio/partners/' . $id);
    }

    /** @param array<string, mixed> $q @param array<string, string> $errors */
    private function questionsPage(array $q, array $errors, int $status = 200): Response
    {
        $uid = (int) $this->context->userId();
        $subjects = $this->suggestions->subjectsOf($uid);
        if ($subjects === []) {
            throw new HttpException(403);
        }

        return Response::html($this->view->render('partner/questions', [
            'title' => 'Suggest questions',
            'q' => $q,
            'errors' => $errors,
            'names' => array_intersect_key(Subjects::NAMES, array_flip($subjects)),
            'types' => Marker::TYPES,
            'topicOptions' => array_values(array_map(static fn (array $o): array => ['label' => 'Form ' . $o['form'] . ' · ' . (Subjects::NAMES[$o['subject']] ?? '') . ' · ' . $o['label']] + $o,
                array_filter($this->topics->options(), static fn (array $o): bool => in_array($o['subject'], $subjects, true)))),
            'mine' => $this->suggestions->mine($uid),
        ]), $status);
    }
}
