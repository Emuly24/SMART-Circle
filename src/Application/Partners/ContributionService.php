<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Data\Repositories\ContributionRepository;
use SmartCircle\Support\Subjects;
use SmartCircle\Support\UploadedFile;

/**
 * Partner teachers help keep SMART Circle accurate: they confirm their school's calendar (or report a
 * mistake), share resources (uploaded here, or sent to the tutor's WhatsApp), and suggest topics the
 * drafted syllabus missed. The admin reviews everything; suggested topics only appear once added.
 */
final class ContributionService
{
    public const KINDS = [
        'syllabus' => 'Syllabus',
        'scheme_of_work' => 'Scheme of work',
        'past_paper' => 'Past paper or test',
        'notes' => 'Notes or handout',
        'other' => 'Something else',
    ];
    public const MAX_BYTES = 9 * 1024 * 1024;
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
    ];

    public function __construct(
        private readonly ContributionRepository $repo,
        private readonly CalendarService $calendar,
        private readonly TopicService $topics,
        private readonly NotificationService $notifications,
        private readonly string $storageRoot,
        /** @var \Closure(): ?string the tutor's WhatsApp number */
        private readonly \Closure $tutorWhatsapp,
    ) {
    }

    /* ---------- Calendar ---------- */

    /** The academic year to confirm: the current one, or the next during holidays. @return array<string, mixed>|null */
    public function year(): ?array
    {
        $id = $this->calendar->currentTermId();
        $term = $id !== null ? $this->calendar->term($id) : null;

        return $term === null ? null : ['id' => (int) $term['year_id'], 'name' => (string) $term['year_name']];
    }

    /** @return array<string, mixed>|null */
    public function calendarStatus(int $schoolId): ?array
    {
        $y = $this->year();

        return $y === null ? null : ['year' => $y, 'latest' => $this->repo->calendarStatus($schoolId, $y['id'])];
    }

    /** @return string|null error */
    public function answerCalendar(int $userId, int $schoolId, string $schoolName, bool $correct, string $note): ?string
    {
        $y = $this->year();
        if ($y === null) {
            return 'The tutor has not entered this year’s terms yet.';
        }
        $note = trim($note);
        if (!$correct && mb_strlen($note) < 5) {
            return 'Tell the tutor what is wrong (for example the right opening date).';
        }
        $this->repo->confirmCalendar($schoolId, $y['id'], $correct ? 'confirmed' : 'problem', $note === '' ? null : mb_substr($note, 0, 1000), $userId);
        if (!$correct) {
            $this->notifications->notifyTutors('calendar', 'Calendar problem reported: ' . $schoolName, mb_substr($note, 0, 200), '/studio/contributions');
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function calendarOverview(): array
    {
        $y = $this->year();

        return ['year' => $y, 'schools' => $y === null ? [] : $this->repo->calendarBySchool($y['id'])];
    }

    public function resolveCalendar(int $id): void
    {
        $this->repo->resolveCalendar($id);
    }

    /* ---------- Resources ---------- */

    /**
     * Validates what a teacher is sharing. Pure.
     * @param array<string, string> $in kind, title, subject, form, note
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validateResource(array $in): array
    {
        $errors = [];
        $kind = $in['kind'] ?? '';
        if (!isset(self::KINDS[$kind])) {
            $errors['kind'] = 'Choose what kind of resource it is.';
        }
        $title = trim($in['title'] ?? '');
        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            $errors['title'] = 'Give it a short name, e.g. “Form 3 Chemistry scheme of work, Term 1”.';
        }
        $subject = $in['subject'] ?? '';
        if ($subject !== '' && !isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose a subject.';
        }
        $form = (int) ($in['form'] ?? 0);
        if (($in['form'] ?? '') !== '' && ($form < 1 || $form > 4)) {
            $errors['form'] = 'Choose a form.';
        }
        $note = trim($in['note'] ?? '');

        return ['errors' => $errors, 'clean' => ['kind' => $kind, 'title' => $title, 'subject' => $subject === '' ? null : $subject,
            'form' => $form >= 1 && $form <= 4 ? $form : null, 'note' => $note === '' ? null : mb_substr($note, 0, 500)]];
    }

    /**
     * Shares a resource: either uploads the file, or records that it goes to the tutor on WhatsApp
     * and returns the prefilled WhatsApp link.
     * @return array{errors: array<string, string>, whatsapp: string|null}
     */
    public function share(int $userId, int $schoolId, string $teacher, string $school, array $in, string $via, ?UploadedFile $file): array
    {
        $v = self::validateResource($in);
        $errors = $v['errors'];
        $mime = '';
        if ($via === 'upload') {
            if ($file === null || !$file->ok()) {
                $errors['file'] = 'Choose the file to upload, or use “Send on WhatsApp” instead.';
            } elseif ($file->size > self::MAX_BYTES) {
                $errors['file'] = 'The file is larger than 9 MB. Send it on WhatsApp instead, or split it.';
            } elseif (!isset(self::TYPES[$mime = $file->mime()])) {
                $errors['file'] = 'Upload a PDF, Word document or photo (JPG, PNG).';
            }
        } elseif ($via !== 'whatsapp') {
            $errors['file'] = 'Choose how to share it.';
        }
        $link = $via === 'whatsapp' ? $this->whatsappLink($teacher, $school, $v['clean']) : null;
        if ($via === 'whatsapp' && $link === null) {
            $errors['file'] = 'The tutor’s WhatsApp number is not set yet. Please upload the file instead.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'whatsapp' => null];
        }
        $id = $this->repo->addResource($v['clean'] + ['school_id' => $schoolId, 'user_id' => $userId, 'via' => $via]);
        if ($via === 'upload' && $file !== null) {
            $relative = 'resources/' . $schoolId . '/' . $id . '.' . self::TYPES[$mime];
            if (!$file->moveTo(rtrim($this->storageRoot, '/') . '/' . $relative)) {
                $this->repo->deleteResource($id);

                return ['errors' => ['file' => 'The file could not be saved. Please try again.'], 'whatsapp' => null];
            }
            $this->repo->attachFile($id, $relative, $mime, $file->size);
        }
        $this->notifications->notifyTutors('resource', 'New resource from ' . $school . ': ' . $v['clean']['title'],
            $via === 'whatsapp' ? 'Sent to you on WhatsApp.' : 'Uploaded to SMART Circle.', '/studio/contributions');

        return ['errors' => [], 'whatsapp' => $link];
    }

    /** A prefilled WhatsApp message to the tutor. @param array<string, mixed> $r kind, title, subject, form */
    public function whatsappLink(string $teacher, string $school, array $r): ?string
    {
        $n = ($this->tutorWhatsapp)();
        if ($n === null) {
            return null;
        }

        return 'https://wa.me/' . ltrim($n, '+') . '?text=' . rawurlencode(self::whatsappText($teacher, $school, $r));
    }

    /** Pure. @param array<string, mixed> $r */
    public static function whatsappText(string $teacher, string $school, array $r): string
    {
        $about = array_filter([
            $r['form'] !== null ? 'Form ' . $r['form'] : null,
            $r['subject'] !== null ? (Subjects::NAMES[$r['subject']] ?? null) : null,
        ]);

        return 'Hello, this is ' . $teacher . ' from ' . $school . ' (SMART Circle partner). I am sending you: ' . $r['title']
            . ' (' . (self::KINDS[$r['kind']] ?? 'resource') . ($about !== [] ? ', ' . implode(' ', $about) : '') . ').';
    }

    /** @return list<array<string, mixed>> */
    public function resources(?int $schoolId): array
    {
        return $this->repo->resources($schoolId);
    }

    /**
     * The stored file of a resource, if this person may open it (the admin, or the school's teachers).
     * @return array{path: string, mime: string, name: string}|null
     */
    public function file(int $id, ?int $schoolId): ?array
    {
        $r = $this->repo->resource($id);
        if ($r === null || $r['file_path'] === null || ($schoolId !== null && (int) $r['school_id'] !== $schoolId)) {
            return null;
        }
        $path = rtrim($this->storageRoot, '/') . '/' . $r['file_path'];
        $ext = pathinfo((string) $r['file_path'], PATHINFO_EXTENSION);
        $name = trim((string) preg_replace('/[^A-Za-z0-9 _-]+/', '', (string) $r['title'])) ?: 'resource';

        return is_file($path) ? ['path' => $path, 'mime' => (string) $r['mime'], 'name' => $name . '.' . $ext] : null;
    }

    public function review(int $id, int $userId): void
    {
        $this->repo->reviewResource($id, $userId);
    }

    /* ---------- Topic suggestions ---------- */

    /**
     * @param array<string, string> $in form, subject, parent_id, title, note
     * @return array<string, string> errors
     */
    public function suggest(int $userId, int $schoolId, string $school, array $allowedSubjects, array $in): array
    {
        $errors = [];
        $form = (int) ($in['form'] ?? 0);
        $subject = $in['subject'] ?? '';
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose a form.';
        }
        if (!isset(Subjects::NAMES[$subject]) || ($allowedSubjects !== [] && !in_array($subject, $allowedSubjects, true))) {
            $errors['subject'] = 'Choose one of your subjects.';
        }
        $title = trim(preg_replace('/\s+/', ' ', $in['title'] ?? '') ?? '');
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors['title'] = 'Type the topic as it appears in the syllabus.';
        }
        $parentId = ($in['parent_id'] ?? '') !== '' ? (int) $in['parent_id'] : null;
        if ($parentId !== null && $errors === []) {
            $p = $this->topics->find($parentId);
            if ($p === null || $p['parent_id'] !== null || (int) $p['form'] !== $form || $p['subject'] !== $subject) {
                $errors['parent_id'] = 'Choose a main topic from the same form and subject, or leave it empty.';
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        $note = trim($in['note'] ?? '');
        $this->repo->addSuggestion(['user_id' => $userId, 'school_id' => $schoolId, 'form' => $form, 'subject' => $subject,
            'parent_id' => $parentId, 'title' => $title, 'note' => $note === '' ? null : mb_substr($note, 0, 1000)]);
        $this->notifications->notifyTutors('suggestion', 'Topic suggested by ' . $school . ': ' . $title, 'Form ' . $form . ' ' . (Subjects::NAMES[$subject] ?? ''), '/studio/contributions');

        return [];
    }

    /** @return list<array<string, mixed>> */
    public function suggestions(?int $schoolId): array
    {
        return $this->repo->suggestions($schoolId);
    }

    /**
     * The admin adds a suggested topic to the catalogue (optionally reworded), or declines it.
     * @return string|null error
     */
    public function decide(int $id, bool $add, string $title, string $note, int $adminId): ?string
    {
        $g = $this->repo->suggestion($id);
        if ($g === null || $g['status'] !== 'pending') {
            return 'This suggestion has already been decided.';
        }
        $note = trim($note) === '' ? null : mb_substr(trim($note), 0, 500);
        $topicId = null;
        if ($add) {
            $r = $this->topics->create((int) $g['form'], (string) $g['subject'], $g['parent_id'] !== null ? (int) $g['parent_id'] : null, trim($title) !== '' ? $title : (string) $g['title']);
            if ($r['errors'] !== []) {
                return implode(' ', $r['errors']);
            }
            $topicId = $r['id'];
        }
        $this->repo->decide($id, $add ? 'added' : 'declined', $topicId, $note, $adminId);
        if ($g['user_id'] !== null) {
            $this->notifications->notify([(int) $g['user_id']], 'suggestion',
                $add ? 'Your topic was added: ' . $g['title'] : 'About your topic suggestion: ' . $g['title'],
                $note ?? ($add ? 'Thank you for helping keep the syllabus complete.' : null), '/partner/contribute');
        }

        return null;
    }

    /** @return array<string, int> */
    public function waiting(): array
    {
        return $this->repo->waiting();
    }

    /** Main topics for a form and subject, to suggest a subtopic under. @return list<array<string, mixed>> */
    public function mainTopics(): array
    {
        return array_values(array_filter($this->topics->options(), static fn (array $o): bool => !str_contains((string) $o['label'], ' › ')));
    }
}
