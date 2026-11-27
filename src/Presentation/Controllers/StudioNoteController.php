<?php

declare(strict_types=1);

namespace SmartCircle\Presentation\Controllers;

use SmartCircle\Application\Notes\NoteService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Presentation\Http\Context;
use SmartCircle\Presentation\Http\HttpException;
use SmartCircle\Presentation\Http\Request;
use SmartCircle\Presentation\Http\Response;
use SmartCircle\Presentation\Http\Session;
use SmartCircle\Presentation\View\View;
use SmartCircle\Support\Subjects;

/** Tutor: write notes and slides, and place checkpoint exercises in them. */
final class StudioNoteController
{
    private const FIELDS = ['kind', 'title', 'form', 'subject', 'topic_id', 'summary'];

    public function __construct(
        private readonly NoteService $notes,
        private readonly TopicService $topics,
        private readonly Context $context,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('studio/notes/index', [
            'title' => 'Notes & slides',
            'notes' => array_values(array_filter($this->notes->all(), fn (array $n): bool => $this->context->canTeach((string) $n['subject']))),
            'names' => $this->subjectNames(),
            'kinds' => NoteService::KINDS,
        ]));
    }

    public function create(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->detailsPage(['id' => null, 'kind' => 'notes', 'form' => 1, 'subject' => array_key_first($this->subjectNames())], []);
        }
        $input = $this->input($request);
        $r = $this->notes->save(null, $input, (int) $this->context->userId());
        if ($r['errors'] !== []) {
            return $this->detailsPage(['id' => null] + $input, $r['errors'], 422);
        }
        $this->session->flash('success', 'Created. Now add your notes, slides and checkpoints.');

        return Response::redirect('/studio/notes/' . $r['id']);
    }

    public function edit(Request $request): Response
    {
        return $this->editor($this->noteOr404($request), []);
    }

    public function update(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $input = $this->input($request);
        $r = $this->notes->save((int) $note['id'], $input, (int) $this->context->userId());
        if ($r['errors'] !== []) {
            return $this->editor(array_merge($note, $input), $r['errors'], 422);
        }
        $this->session->flash('success', 'Details saved.');

        return Response::redirect('/studio/notes/' . $note['id']);
    }

    public function addText(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->flash($this->notes->addText((int) $note['id'], $request->input('body')), 'Section added.');

        return Response::redirect('/studio/notes/' . $note['id'] . '#add');
    }

    public function addImage(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->flash($this->notes->addImage((int) $note['id'], $request->file('image'), $request->input('caption'), $request->input('align', 'center')), 'Picture added.');

        return Response::redirect('/studio/notes/' . $note['id'] . '#add');
    }

    public function addCheckpoint(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $assessmentId = $this->notes->addCheckpoint((int) $note['id'], $request->input('final') === '1', (int) $this->context->userId());
        $this->session->flash('success', 'Checkpoint added. Now choose its questions from the bank.');

        return Response::redirect('/studio/assessments/' . $assessmentId);
    }

    public function updateBlock(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->flash($this->notes->updateBlock((int) $note['id'], (int) $request->param('block'), $request->input('body')), 'Saved.');

        return Response::redirect('/studio/notes/' . $note['id'] . '#block-' . (int) $request->param('block'));
    }

    public function alignBlock(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->flash($this->notes->setAlign((int) $note['id'], (int) $request->param('block'), $request->input('align')), 'Saved.');

        return Response::redirect('/studio/notes/' . $note['id'] . '#block-' . (int) $request->param('block'));
    }

    public function deleteBlock(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->flash($this->notes->deleteBlock((int) $note['id'], (int) $request->param('block')), 'Removed.');

        return Response::redirect('/studio/notes/' . $note['id']);
    }

    public function moveBlock(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $this->notes->moveBlock((int) $note['id'], (int) $request->param('block'), $request->input('direction') === 'up' ? -1 : 1);

        return Response::redirect('/studio/notes/' . $note['id'] . '#block-' . (int) $request->param('block'));
    }

    public function action(Request $request): Response
    {
        $note = $this->noteOr404($request);
        $id = (int) $note['id'];
        switch ($request->param('action')) {
            case 'publish':
                $this->flash($this->notes->publish($id), 'Published. Students whose school has taught this topic have been told.');
                break;
            case 'unpublish':
                $this->notes->unpublish($id);
                $this->session->flash('info', 'Unpublished. Students can no longer open it.');
                break;
            case 'delete':
                $error = $this->notes->delete($id);
                if ($error === '') {
                    $this->session->flash('info', 'Deleted.');
                    return Response::redirect('/studio/notes');
                }
                $this->session->flash('error', $error);
                break;
            default:
                throw new HttpException(404);
        }

        return Response::redirect('/studio/notes/' . $id);
    }

    public function image(Request $request): Response
    {
        $path = $this->notes->imagePath((int) $request->param('id'), (int) $request->param('block'), $this->context->userId(), true)
            ?? throw new HttpException(404);

        return NoteController::imageResponse($path);
    }

    private function flash(string $error, string $success): void
    {
        $this->session->flash(...($error === '' ? ['success', $success] : ['error', $error]));
    }

    /** @param array<string, mixed> $note @param array<string, string> $errors */
    private function editor(array $note, array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/notes/edit', [
            'title' => (string) $note['title'],
            'note' => $note,
            'blocks' => $this->notes->blocksForEditor((int) $note['id']),
            'errors' => $errors,
            'names' => $this->subjectNames(),
            'kinds' => NoteService::KINDS,
            'topicOptions' => $this->topicOptions((int) $note['form'], (string) $note['subject']),
        ]), $status);
    }

    /** @param array<string, mixed> $note @param array<string, string> $errors */
    private function detailsPage(array $note, array $errors, int $status = 200): Response
    {
        return Response::html($this->view->render('studio/notes/new', [
            'title' => 'New notes or slides',
            'note' => $note,
            'errors' => $errors,
            'names' => $this->subjectNames(),
            'kinds' => NoteService::KINDS,
            'allTopics' => $this->topics->options(),
        ]), $status);
    }

    /** @return list<array{id: int, form: int, subject: string, label: string}> */
    private function topicOptions(int $form, string $subject): array
    {
        return array_values(array_filter($this->topics->options(), static fn (array $o): bool => $o['form'] === $form && $o['subject'] === $subject));
    }

    /** @return array<string, string> */
    private function input(Request $request): array
    {
        $in = [];
        foreach (self::FIELDS as $f) {
            $in[$f] = $request->input($f);
        }

        return $in;
    }

    /** @return array<string, mixed> */
    private function noteOr404(Request $request): array
    {
        return $this->notes->find((int) $request->param('id')) ?? throw new HttpException(404);
    }

    /** Subjects this person may choose (team tutors: only their own). @return array<string, string> */
    private function subjectNames(): array
    {
        $scope = $this->context->subjects();

        return $scope === null ? Subjects::NAMES : array_intersect_key(Subjects::NAMES, array_flip($scope));
    }
}
