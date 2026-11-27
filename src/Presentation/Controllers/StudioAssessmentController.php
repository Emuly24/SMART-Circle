<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\Marker;
use SmartCircle\Application\Assessments\RecheckService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Tutor: question bank, assessment builder, marking. */
final class StudioAssessmentController
{
    private const QUESTION_FIELDS = ['form', 'subject', 'topic_id', 'type', 'prompt', 'marks', 'is_application',
        'correct', 'answer', 'tolerance_pct', 'unit', 'unit_required', 'accepted', 'model_answer'];
    private const DETAIL_FIELDS = ['kind', 'title', 'form', 'subject', 'instructions', 'opens_at', 'closes_at', 'duration_min'];

    public function __construct(
        private readonly AssessmentService $assessments,
        private readonly TopicService $topics,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
        private readonly ?RecheckService $rechecks = null,
    ) {
    }

    /* ---------- Question bank ---------- */

    public function bank(Request $request): Response
    {
        [$form, $subject] = $this->formSubject($request->query('form', '1'), $request->query('subject', 'mathematics'));

        return Response::html($this->view->render('studio/questions/index', [
            'title' => 'Question bank',
            'form' => $form,
            'subject' => $subject,
            'questions' => $this->assessments->bank($form, $subject),
            'names' => $this->subjectNames(),
            'types' => Marker::TYPES,
        ]));
    }

    public function newQuestion(Request $request): Response
    {
        [$form, $subject] = $this->formSubject($request->query('form', '1'), $request->query('subject', 'mathematics'));

        return $this->questionForm(['id' => null, 'form' => $form, 'subject' => $subject, 'type' => 'mcq', 'marks' => 1, 'data' => []], []);
    }

    public function editQuestion(Request $request): Response
    {
        return $this->questionForm($this->assessments->question((int) $request->param('id')) ?? throw new HttpException(404), []);
    }

    public function saveQuestion(Request $request): Response
    {
        $id = $request->param('id') !== '' ? (int) $request->param('id') : null;
        if ($id !== null && $this->assessments->question($id) === null) {
            throw new HttpException(404);
        }
        $input = $this->questionInput($request);
        $result = $this->assessments->saveQuestion($id, $input, (int) $this->context->userId());
        if ($result['errors'] !== []) {
            return $this->questionForm($this->asQuestion($id, $input), $result['errors'], 422);
        }
        $this->session->flash('success', 'Question saved.');

        return Response::redirect('/studio/questions?form=' . (int) $input['form'] . '&subject=' . $input['subject']);
    }

    public function deleteQuestion(Request $request): Response
    {
        $q = $this->assessments->question((int) $request->param('id')) ?? throw new HttpException(404);
        $this->session->flash(...($this->assessments->deleteQuestion((int) $q['id'])
            ? ['info', 'Question deleted.'] : ['error', 'This question is used in an assessment. Remove it there first.']));

        return Response::redirect('/studio/questions?form=' . $q['form'] . '&subject=' . $q['subject']);
    }

    /* ---------- Assessments ---------- */

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('studio/assessments/index', [
            'title' => 'Exams & assignments',
            'assessments' => $this->mine($this->assessments->all()),
            'names' => $this->subjectNames(),
            'kinds' => AssessmentService::KINDS,
        ]));
    }

    public function create(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->detailsForm(['id' => null, 'kind' => 'exam', 'form' => 1, 'subject' => array_key_first($this->subjectNames()), 'duration_min' => 40], []);
        }
        $input = $this->detailsInput($request);
        $result = $this->assessments->save(null, $input, (int) $this->context->userId());
        if ($result['errors'] !== []) {
            return $this->detailsForm(['id' => null] + $input, $result['errors'], 422);
        }
        $this->session->flash('success', 'Created. Now add questions from the bank.');

        return Response::redirect('/studio/assessments/' . $result['id']);
    }

    public function show(Request $request): Response
    {
        $a = $this->assessmentOr404($request);

        return $this->builder($a, []);
    }

    public function update(Request $request): Response
    {
        $a = $this->assessmentOr404($request);
        $input = $this->detailsInput($request);
        $result = $this->assessments->save((int) $a['id'], $input, (int) $this->context->userId());
        if ($result['errors'] !== []) {
            return $this->builder(array_merge($a, $input), $result['errors'], 422);
        }
        $this->session->flash('success', 'Details saved.');

        return Response::redirect('/studio/assessments/' . $a['id']);
    }

    public function addQuestion(Request $request): Response
    {
        $a = $this->assessmentOr404($request);
        $error = $this->assessments->addQuestion((int) $a['id'], (int) $request->input('question_id'));
        $this->session->flash(...($error === '' ? ['success', 'Question added.'] : ['error', $error]));

        return Response::redirect('/studio/assessments/' . $a['id'] . '#questions');
    }

    public function removeQuestion(Request $request): Response
    {
        $a = $this->assessmentOr404($request);
        $error = $this->assessments->removeQuestion((int) $a['id'], (int) $request->param('question'));
        $this->session->flash(...($error === '' ? ['info', 'Question removed.'] : ['error', $error]));

        return Response::redirect('/studio/assessments/' . $a['id'] . '#questions');
    }

    public function moveQuestion(Request $request): Response
    {
        $a = $this->assessmentOr404($request);
        $this->assessments->moveQuestion((int) $a['id'], (int) $request->param('question'), $request->input('direction') === 'up' ? -1 : 1);

        return Response::redirect('/studio/assessments/' . $a['id'] . '#questions');
    }

    public function action(Request $request): Response
    {
        $a = $this->assessmentOr404($request);
        $id = (int) $a['id'];
        switch ($request->param('action')) {
            case 'publish':
                $error = $this->assessments->publish($id);
                $done = 'Published. Students whose school has taught every topic in it have been told.';
                break;
            case 'close':
                $this->assessments->close($id);
                [$error, $done] = ['', 'Closed. No new answers are accepted.'];
                break;
            case 'reopen':
                $this->assessments->reopen($id);
                [$error, $done] = ['', 'Open again.'];
                break;
            case 'release':
                $error = $this->assessments->releaseResults($id);
                $done = 'Results released. Students have been told.';
                break;
            case 'delete':
                $error = $this->assessments->delete($id);
                if ($error === '') {
                    $this->session->flash('info', 'Deleted.');
                    return Response::redirect('/studio/assessments');
                }
                $done = '';
                break;
            default:
                throw new HttpException(404);
        }
        $this->session->flash(...($error === '' ? ['success', $done] : ['error', $error]));

        return Response::redirect('/studio/assessments/' . $id);
    }

    /* ---------- Marking ---------- */

    public function submissions(Request $request): Response
    {
        $a = $this->assessmentOr404($request);

        return Response::html($this->view->render('studio/assessments/submissions', [
            'title' => 'Submissions: ' . $a['title'],
            'a' => $a,
            'rows' => $this->assessments->submissions((int) $a['id']),
        ]));
    }

    public function markForm(Request $request): Response
    {
        return $this->markPage((int) $request->param('id'), [], []);
    }

    public function mark(Request $request): Response
    {
        $id = (int) $request->param('id');
        $all = $request->allInput();
        $scores = is_array($all['score'] ?? null) ? $all['score'] : [];
        $feedback = is_array($all['feedback'] ?? null) ? $all['feedback'] : [];
        $errors = $this->assessments->mark($id, $scores, $feedback, $request->input('overall'), (int) $this->context->userId());
        if ($errors !== []) {
            return $this->markPage($id, $errors, ['score' => $scores, 'feedback' => $feedback, 'overall' => $request->input('overall')], 422);
        }
        $view = $this->assessments->markingView($id);
        $this->session->flash('success', 'Marks saved.');

        return Response::redirect('/studio/assessments/' . ($view['submission']['assessment_id'] ?? '') . '/submissions');
    }

    /* ---------- Marking queue and re-checks ---------- */

    public function queue(Request $request): Response
    {
        return Response::html($this->view->render('studio/marking', [
            'title' => 'Marking',
            'rows' => $this->mine($this->assessments->toMark()),
            'openRechecks' => $this->rechecks?->countOpen() ?? 0,
            'kinds' => AssessmentService::KINDS + ['note_check' => 'Checkpoint in notes'],
        ]));
    }

    public function rechecks(Request $request): Response
    {
        return Response::html($this->view->render('studio/rechecks/index', [
            'title' => 'Re-check requests',
            'rows' => $this->mine($this->rechecks?->all() ?? []),
        ]));
    }

    public function recheck(Request $request): Response
    {
        return $this->recheckPage((int) $request->param('id'), [], []);
    }

    public function resolveRecheck(Request $request): Response
    {
        $id = (int) $request->param('id');
        $in = ['decision' => $request->input('decision'), 'new_score' => $request->input('new_score'), 'tutor_reply' => $request->input('tutor_reply')];
        $errors = $this->rechecks?->resolve($id, $in['decision'], $in['new_score'], $in['tutor_reply'], (int) $this->context->userId()) ?? ['decision' => 'Not available.'];
        if ($errors !== []) {
            return $this->recheckPage($id, $errors, $in, 422);
        }
        $this->session->flash('success', 'Reply sent to the student.');

        return Response::redirect('/studio/rechecks');
    }

    /** @param array<string, string> $errors @param array<string, string> $old */
    private function recheckPage(int $id, array $errors, array $old, int $status = 200): Response
    {
        $r = $this->rechecks?->find($id) ?? throw new HttpException(404);

        return Response::html($this->view->render('studio/rechecks/show', [
            'title' => 'Re-check: ' . $r['full_name'],
            'r' => $r,
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /* ---------- Helpers ---------- */

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function markPage(int $id, array $errors, array $old, int $status = 200): Response
    {
        $view = $this->assessments->markingView($id) ?? throw new HttpException(404);

        return Response::html($this->view->render('studio/assessments/mark', [
            'title' => 'Mark: ' . $view['submission']['full_name'],
            'v' => $view,
            'errors' => $errors,
            'old' => $old,
            'types' => Marker::TYPES,
        ]), $status);
    }

    /** @param array<string, mixed> $q @param array<string, string> $errors */
    private function questionForm(array $q, array $errors, int $status = 200): Response
    {
        $form = (int) $q['form'];
        $subject = (string) $q['subject'];

        return Response::html($this->view->render('studio/questions/edit', [
            'title' => $q['id'] === null ? 'New question' : 'Edit question',
            'q' => $q,
            'errors' => $errors,
            'names' => $this->subjectNames(),
            'types' => Marker::TYPES,
            'topicOptions' => array_values(array_filter($this->topics->options(),
                static fn (array $o): bool => $o['form'] === $form && $o['subject'] === $subject)),
        ]), $status);
    }

    /** @param array<string, mixed> $a @param array<string, string> $errors */
    private function builder(array $a, array $errors, int $status = 200): Response
    {
        $inIds = array_map(static fn (array $q): int => (int) $q['id'], $this->assessments->questionsIn((int) $a['id']));

        return Response::html($this->view->render('studio/assessments/edit', [
            'title' => (string) $a['title'],
            'a' => $a,
            'errors' => $errors,
            'questions' => $this->assessments->questionsIn((int) $a['id']),
            'bank' => array_values(array_filter($this->assessments->bank((int) $a['form'], (string) $a['subject']),
                static fn (array $q): bool => !in_array((int) $q['id'], $inIds, true))),
            'submissions' => $this->assessments->submissions((int) $a['id']),
            'names' => $this->subjectNames(),
            'kinds' => AssessmentService::KINDS,
            'types' => Marker::TYPES,
        ]), $status);
    }

    /** @param array<string, mixed> $a @param array<string, string> $errors */
    private function detailsForm(array $a, array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/assessments/new', [
            'title' => 'New exam or assignment',
            'a' => $a,
            'errors' => $errors,
            'names' => $this->subjectNames(),
            'kinds' => AssessmentService::KINDS,
        ]), $status);
    }

    /** @return array<string, mixed> */
    private function questionInput(Request $request): array
    {
        $in = [];
        foreach (self::QUESTION_FIELDS as $f) {
            $in[$f] = $request->input($f);
        }
        $in['options'] = $request->inputList('options');

        return $in;
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private function asQuestion(?int $id, array $in): array
    {
        $data = match ($in['type']) {
            'mcq' => ['options' => $in['options'], 'correct' => $in['correct']],
            'numeric' => ['answer' => $in['answer'], 'tolerance_pct' => $in['tolerance_pct'], 'unit' => $in['unit'], 'unit_required' => $in['unit_required'] === '1'],
            'grammar_fill' => ['accepted' => array_filter(explode("\n", (string) $in['accepted']))],
            default => ['model_answer' => $in['model_answer']],
        };

        return ['id' => $id] + $in + ['data' => $data];
    }

    /** @return array<string, string> */
    private function detailsInput(Request $request): array
    {
        $in = [];
        foreach (self::DETAIL_FIELDS as $f) {
            $in[$f] = $request->input($f);
        }

        return $in;
    }

    /** @return array{0: int, 1: string} */
    private function formSubject(string $form, string $subject): array
    {
        $names = $this->subjectNames();

        return [max(1, min(4, (int) $form)), isset($names[$subject]) ? $subject : (string) array_key_first($names)];
    }

    /** @return array<string, mixed> */
    private function assessmentOr404(Request $request): array
    {
        return $this->assessments->find((int) $request->param('id')) ?? throw new HttpException(404);
    }

    /** Subjects this person may choose (team tutors: only their own). @return array<string, string> */
    private function subjectNames(): array
    {
        $scope = $this->context->subjects();

        return $scope === null ? Subjects::NAMES : array_intersect_key(Subjects::NAMES, array_flip($scope));
    }

    /** Team tutors only see rows in their own subjects. @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function mine(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $r): bool => $this->context->canTeach(isset($r['subject']) ? (string) $r['subject'] : null)));
    }
}
