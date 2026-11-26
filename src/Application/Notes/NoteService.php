<?php

declare(strict_types=1);

namespace SmartCircle\Application\Notes;

use SmartCircle\Application\Assessments\AssessmentService;
use SmartCircle\Application\Assessments\ExamService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\AssessmentRepository;
use SmartCircle\Data\Repositories\NoteRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\SubmissionRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * Notes and slides with checkpoint exercises.
 *  - Notes are blocks in order: text (simple markup), images (slides, diagrams) and checkpoints.
 *  - A student reads up to the first checkpoint they have not submitted; everything after it is
 *    never sent to their device. Submitting opens the next part (marking can come later).
 *  - The honesty rule applies: notes open once the student's school has taught their topic.
 */
final class NoteService
{
    public const KINDS = ['notes' => 'Written notes', 'slides' => 'Slides'];
    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly NoteRepository $notes,
        private readonly AssessmentRepository $assessments,
        private readonly SubmissionRepository $submissions,
        private readonly TopicRepository $topics,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
        private readonly ExamService $exams,
        private readonly NotificationService $notifications,
        private readonly string $storageRoot,
    ) {
    }

    /* ---------- Tutor ---------- */

    /**
     * @param array<string, mixed> $in kind, title, form, subject, topic_id, summary
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public function validate(array $in): array
    {
        $kind = (string) ($in['kind'] ?? 'notes');
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        $form = (int) ($in['form'] ?? 0);
        $subject = (string) ($in['subject'] ?? '');
        $topicId = (int) ($in['topic_id'] ?? 0);
        $errors = [];
        if (!isset(self::KINDS[$kind])) {
            $errors['kind'] = 'Choose notes or slides.';
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors['title'] = 'Give it a title (3–200 characters).';
        }
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose the form.';
        }
        if (!isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose the subject.';
        }
        $topic = $topicId > 0 ? $this->topics->find($topicId) : null;
        if ($topic === null || (int) $topic['form'] !== $form || $topic['subject'] !== $subject) {
            $errors['topic_id'] = 'Choose the topic (from the same form and subject). Students get these notes once their school has taught it.';
        }

        return ['errors' => $errors, 'clean' => [
            'kind' => $kind, 'title' => $title, 'form' => $form, 'subject' => $subject, 'topic_id' => $topicId,
            'summary' => trim((string) ($in['summary'] ?? '')) ?: null,
        ]];
    }

    /** @return array{errors: array<string, string>, id: ?int} */
    public function save(?int $id, array $input, int $tutorId): array
    {
        $v = $this->validate($input);
        if ($v['errors'] !== []) {
            return ['errors' => $v['errors'], 'id' => null];
        }
        if ($id === null) {
            return ['errors' => [], 'id' => $this->notes->create($v['clean'], $tutorId)];
        }
        $current = $this->notes->find($id);
        $hasCheckpoints = array_filter($this->notes->blocks($id), static fn (array $b): bool => $b['type'] === 'checkpoint') !== [];
        if ($current !== null && $hasCheckpoints && ((int) $current['form'] !== $v['clean']['form'] || $current['subject'] !== $v['clean']['subject'])) {
            return ['errors' => ['form' => 'Remove the checkpoints first to change the form or subject.'], 'id' => null];
        }
        $this->notes->update($id, $v['clean']);

        return ['errors' => [], 'id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->notes->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->notes->find($id);
    }

    /**
     * Blocks for the editor, each checkpoint with its question count.
     * @return list<array<string, mixed>>
     */
    public function blocksForEditor(int $noteId): array
    {
        $out = [];
        $n = 0;
        foreach ($this->notes->blocks($noteId) as $b) {
            if ($b['type'] === 'checkpoint') {
                $b['number'] = ++$n;
                $b['questions'] = $b['assessment_id'] !== null ? count($this->assessments->questions((int) $b['assessment_id'])) : 0;
                $b['answered'] = $b['assessment_id'] !== null ? count($this->submissions->forAssessment((int) $b['assessment_id'])) : 0;
            }
            $out[] = $b;
        }

        return $out;
    }

    public function addText(int $noteId, string $body): string
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            return 'Write something first.';
        }
        $this->notes->addBlock($noteId, 'text', mb_substr($body, 0, 60000));

        return '';
    }

    public function addImage(int $noteId, ?UploadedFile $file, string $caption): string
    {
        if ($file === null || !$file->ok()) {
            return 'The picture did not upload. Try again, or use a smaller picture.';
        }
        if ($file->size > self::MAX_IMAGE_BYTES) {
            return 'The picture is too large (maximum 5 MB). Export the slide at a smaller size.';
        }
        $mime = $file->mime();
        if (!isset(self::IMAGE_TYPES[$mime])) {
            return 'Use a JPG, PNG or WebP picture (export slides as pictures).';
        }
        $relative = 'uploads/notes/' . $noteId . '/' . bin2hex(random_bytes(8)) . '.' . self::IMAGE_TYPES[$mime];
        if (!$file->moveTo($this->storageRoot . '/' . $relative)) {
            return 'The picture could not be saved. Please try again.';
        }
        $this->notes->addBlock($noteId, 'image', mb_substr(trim($caption), 0, 500) ?: null, $relative);

        return '';
    }

    /** A checkpoint exercise; questions are then added from the bank in the exercise builder. */
    public function addCheckpoint(int $noteId, bool $final, int $tutorId): int
    {
        $note = $this->notes->find($noteId) ?? throw new \RuntimeException('Note not found');
        $count = count(array_filter($this->notes->blocks($noteId), static fn (array $b): bool => $b['type'] === 'checkpoint'));
        $title = ($final ? 'End of unit exercise' : 'Checkpoint ' . ($count + 1)) . ': ' . $note['title'];
        $assessmentId = $this->assessments->createForNote($noteId, mb_substr($title, 0, 200), (int) $note['form'], (string) $note['subject'], $tutorId);
        if ($note['status'] === 'published') {
            $this->assessments->setStatus($assessmentId, 'published');
        }
        $this->notes->addBlock($noteId, 'checkpoint', null, null, $assessmentId, $final);

        return $assessmentId;
    }

    public function updateBlock(int $noteId, int $blockId, string $body): string
    {
        $b = $this->notes->block($blockId);
        if ($b === null || (int) $b['note_id'] !== $noteId || $b['type'] === 'checkpoint') {
            return 'That part cannot be edited here.';
        }
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($b['type'] === 'text' && $body === '') {
            return 'Write something, or delete this part.';
        }
        $this->notes->updateBody($blockId, $body === '' ? null : mb_substr($body, 0, 60000));

        return '';
    }

    public function deleteBlock(int $noteId, int $blockId): string
    {
        $b = $this->notes->block($blockId);
        if ($b === null || (int) $b['note_id'] !== $noteId) {
            return 'Not found.';
        }
        if ($b['type'] === 'checkpoint' && $b['assessment_id'] !== null) {
            if ($this->submissions->forAssessment((int) $b['assessment_id']) !== []) {
                return 'Students have answered this checkpoint, so it cannot be deleted.';
            }
            $this->assessments->delete((int) $b['assessment_id']);
        }
        if ($b['image_path'] !== null) {
            @unlink($this->storageRoot . '/' . $b['image_path']);
        }
        $this->notes->deleteBlock($blockId);

        return '';
    }

    public function moveBlock(int $noteId, int $blockId, int $direction): void
    {
        $this->notes->moveBlock($noteId, $blockId, $direction < 0 ? -1 : 1);
    }

    /** Publish the notes and their checkpoints, and tell the students who can read them. */
    public function publish(int $noteId): string
    {
        $note = $this->notes->find($noteId);
        if ($note === null) {
            return 'Not found.';
        }
        $blocks = $this->notes->blocks($noteId);
        if (array_filter($blocks, static fn (array $b): bool => $b['type'] !== 'checkpoint') === []) {
            return 'Add some notes or slides before publishing.';
        }
        foreach ($blocks as $b) {
            if ($b['type'] === 'checkpoint' && ($b['assessment_id'] === null || $this->assessments->questions((int) $b['assessment_id']) === [])) {
                return 'Every checkpoint needs at least one question. Open it and add questions from the bank.';
            }
        }
        $this->notes->setStatus($noteId, 'published');
        $this->assessments->setStatusForNote($noteId, 'published');

        $readers = [];
        foreach ($this->access->allStudents() as $s) {
            if ((int) $s['form'] === (int) $note['form'] && in_array($note['subject'], Subjects::forTrack((string) $s['track']), true)) {
                $decide = $this->access->forStudent((int) $s['user_id']);
                if ($decide !== null && $decide((int) $note['topic_id'])['open']) {
                    $readers[] = (int) $s['user_id'];
                }
            }
        }
        $this->notifications->notify($readers, 'notes_published', 'New ' . ($note['kind'] === 'slides' ? 'slides' : 'notes') . ': ' . $note['title'],
            'Read them and do the exercises as you go.', '/notes/' . $noteId);

        return '';
    }

    public function unpublish(int $noteId): void
    {
        $this->notes->setStatus($noteId, 'draft');
        $this->assessments->setStatusForNote($noteId, 'draft');
    }

    public function delete(int $noteId): string
    {
        foreach ($this->notes->blocks($noteId) as $b) {
            if ($b['assessment_id'] !== null && $this->submissions->forAssessment((int) $b['assessment_id']) !== []) {
                return 'Students have answered its exercises. Unpublish it instead.';
            }
        }
        foreach ($this->notes->blocks($noteId) as $b) {
            if ($b['image_path'] !== null) {
                @unlink($this->storageRoot . '/' . $b['image_path']);
            }
        }
        $this->notes->delete($noteId);

        return '';
    }

    /* ---------- Students ---------- */

    /**
     * Published notes for the student's form and subjects, with access and progress.
     * @return list<array<string, mixed>>
     */
    public function listFor(int $userId): array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return [];
        }
        $out = [];
        $notes = [];
        foreach ($profile['forms'] as $form) {
            array_push($notes, ...$this->notes->publishedForForm((int) $form));
        }
        foreach ($notes as $n) {
            if (!in_array($n['subject'], Subjects::forTrack((string) $profile['track']), true)) {
                continue;
            }
            $n['access'] = $decide((int) $n['topic_id']);
            $done = 0;
            foreach ($this->notes->blocks((int) $n['id']) as $b) {
                if ($b['type'] === 'checkpoint' && $b['assessment_id'] !== null && self::submitted($this->submissions->forStudent((int) $b['assessment_id'], $userId))) {
                    $done++;
                }
            }
            $n['done'] = $done;
            $out[] = $n;
        }

        return $out;
    }

    /**
     * What the student may see: blocks up to and including the first checkpoint they have not
     * submitted. Later blocks are not returned at all.
     * @param array<int, string> $checkpointStates assessment id => submitted | open
     * @param list<array<string, mixed>> $blocks
     * @return array{visible: list<array<string, mixed>>, hidden: int, active: ?int}
     */
    public static function gate(array $blocks, array $checkpointStates): array
    {
        $visible = [];
        foreach ($blocks as $i => $b) {
            $visible[] = $b;
            if ($b['type'] === 'checkpoint' && ($checkpointStates[(int) $b['assessment_id']] ?? 'open') !== 'submitted') {
                return ['visible' => $visible, 'hidden' => count($blocks) - $i - 1, 'active' => (int) $b['id']];
            }
        }

        return ['visible' => $visible, 'hidden' => 0, 'active' => null];
    }

    /**
     * The reader for one student, or null if the notes are not theirs to see.
     * @return array<string, mixed>|null
     */
    public function read(int $noteId, int $userId): ?array
    {
        $note = null;
        foreach ($this->listFor($userId) as $n) {
            if ((int) $n['id'] === $noteId) {
                $note = $n;
            }
        }
        if ($note === null) {
            return null;
        }
        if (!$note['access']['open']) {
            return ['note' => $note, 'locked' => true];
        }
        $blocks = $this->notes->blocks($noteId);
        $states = [];
        $checkpoints = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'checkpoint' && $b['assessment_id'] !== null) {
                $aid = (int) $b['assessment_id'];
                $view = $this->exams->forStudent($aid, $userId);
                $states[$aid] = $view !== null && self::submitted($view['submission']) ? 'submitted' : 'open';
                $checkpoints[$aid] = $view;
            }
        }
        $gate = self::gate($blocks, $states);
        foreach ($gate['visible'] as &$b) {
            if ($b['type'] === 'checkpoint' && $b['assessment_id'] !== null) {
                $aid = (int) $b['assessment_id'];
                $b['view'] = $checkpoints[$aid];
                $b['state'] = $states[$aid];
                if ($states[$aid] !== 'submitted') {
                    // The exercise itself, without answers (the exam engine hides them).
                    $this->exams->start($aid, $userId);
                    $b['attempt'] = $this->exams->attempt($aid, $userId);
                }
            }
        }
        unset($b);

        return ['note' => $note, 'locked' => false, 'blocks' => $gate['visible'], 'hidden' => $gate['hidden'],
            'total_checkpoints' => count($checkpoints), 'done' => count(array_filter($states, static fn (string $s): bool => $s === 'submitted'))];
    }

    /**
     * Submit the checkpoint the student is on (no skipping ahead).
     * @param array<string, mixed> $answers
     * @return array{error: string, state: ?string}
     */
    public function submitCheckpoint(int $noteId, int $blockId, int $userId, array $answers): array
    {
        $r = $this->read($noteId, $userId);
        if ($r === null || $r['locked']) {
            return ['error' => 'These notes are not open to you.', 'state' => null];
        }
        $active = null;
        foreach ($r['blocks'] as $b) {
            if ((int) $b['id'] === $blockId && $b['type'] === 'checkpoint' && $b['state'] !== 'submitted') {
                $active = $b;
            }
        }
        if ($active === null) {
            return ['error' => 'Finish the exercise you are on first.', 'state' => null];
        }
        $res = $this->exams->save((int) $active['assessment_id'], $userId, $answers, true);

        return ['error' => $res['error'], 'state' => $res['state']];
    }

    /** Absolute path of an image the viewer may see, or null. */
    public function imagePath(int $noteId, int $blockId, ?int $userId, bool $isTutor): ?string
    {
        $b = $this->notes->block($blockId);
        if ($b === null || (int) $b['note_id'] !== $noteId || $b['image_path'] === null) {
            return null;
        }
        if (!$isTutor) {
            $r = $userId !== null ? $this->read($noteId, $userId) : null;
            if ($r === null || $r['locked'] || !in_array($blockId, array_map(static fn (array $x): int => (int) $x['id'], $r['blocks']), true)) {
                return null;   // locked notes, or a slide after an unfinished checkpoint
            }
        }
        $path = $this->storageRoot . '/' . $b['image_path'];

        return is_file($path) ? $path : null;
    }

    /** @param array<string, mixed>|null $submission */
    private static function submitted(?array $submission): bool
    {
        return $submission !== null && $submission['status'] !== 'in_progress';
    }

    public static function kindLabel(string $kind): string
    {
        return AssessmentService::KINDS[$kind] ?? ($kind === 'note_check' ? 'Checkpoint in notes' : $kind);
    }
}
