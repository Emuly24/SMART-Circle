<?php

declare(strict_types=1);

namespace SmartCircle\Application\Lessons;

use SmartCircle\Data\LessonStorage;
use SmartCircle\Data\Repositories\LessonRepository;

/**
 * Lesson rules: editing details, publishing, deleting, and who may watch.
 */
final class LessonService
{
    public const SUBJECTS = [
        'mathematics' => 'Mathematics',
        'physics' => 'Physics',
        'chemistry' => 'Chemistry',
        'biology' => 'Biology',
        'english' => 'English grammar',
    ];

    /** Every lesson must answer these before students can see it. */
    public const REAL_LIFE_SECTIONS = [
        'why_it_matters' => 'Why it matters',
        'where_in_malawi' => 'Where you see it in Malawi',
        'careers' => 'Careers that use it',
        'try_at_home' => 'Try it safely at home',
    ];

    public function __construct(
        private readonly LessonRepository $lessons,
        private readonly LessonStorage $storage,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->lessons->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function allForTutor(): array
    {
        return $this->lessons->all();
    }

    /** @return list<array<string, mixed>> */
    public function publishedForForm(?int $form): array
    {
        return $this->lessons->published($form);
    }

    /**
     * @param array<string, string> $input raw form values
     * @return array<string, string> field => error (empty when saved)
     */
    public function updateDetails(int $id, array $input): array
    {
        $title = trim($input['title'] ?? '');
        $form = (int) ($input['form'] ?? 0);
        $subject = $input['subject'] ?? '';

        $errors = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors['title'] = 'Give the lesson a title (3–200 characters).';
        }
        if ($form !== 0 && ($form < 1 || $form > 4)) {
            $errors['form'] = 'Choose Form 1, 2, 3 or 4.';
        }
        if ($subject !== '' && !isset(self::SUBJECTS[$subject])) {
            $errors['subject'] = 'Choose a subject.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $details = [
            'title' => $title,
            'form' => $form === 0 ? null : $form,
            'subject' => $subject === '' ? null : $subject,
            'summary' => self::clean($input['summary'] ?? ''),
        ];
        foreach (array_keys(self::REAL_LIFE_SECTIONS) as $field) {
            $details[$field] = self::clean($input[$field] ?? '');
        }
        $this->lessons->updateDetails($id, $details);

        return [];
    }

    /** @return list<string> what is still missing before the lesson can be published */
    public function missingForPublish(array $lesson): array
    {
        $missing = [];
        if (empty($lesson['form'])) {
            $missing[] = 'Form';
        }
        if (empty($lesson['subject'])) {
            $missing[] = 'Subject';
        }
        foreach (self::REAL_LIFE_SECTIONS as $field => $label) {
            if (trim((string) ($lesson[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** @return list<string> missing items (empty = published) */
    public function publish(int $id): array
    {
        $lesson = $this->lessons->find($id);
        if ($lesson === null) {
            return ['Lesson'];
        }
        $missing = $this->missingForPublish($lesson);
        if ($missing === []) {
            $this->lessons->setStatus($id, 'published');
        }

        return $missing;
    }

    public function unpublish(int $id): void
    {
        $this->lessons->setStatus($id, 'draft');
    }

    public function delete(int $id): void
    {
        $this->lessons->delete($id);
        $this->storage->deleteLesson($id);
    }

    /** Tutors see everything; students only published lessons. Guests nothing. */
    public function canWatch(array $lesson, ?string $role): bool
    {
        return match ($role) {
            'tutor' => true,
            'student' => $lesson['status'] === 'published',
            default => false,
        };
    }

    /** @return list<string> absolute paths of the lesson's stored parts */
    public function fileParts(int $id): array
    {
        return $this->storage->lessonParts($id);
    }

    private static function clean(string $text): ?string
    {
        $text = trim(str_replace("\r\n", "\n", $text));

        return $text === '' ? null : mb_substr($text, 0, 5000);
    }
}
