<?php

declare(strict_types=1);

namespace SmartCircle\Application\Partners;

use SmartCircle\Application\Topics\TopicService;
use SmartCircle\Data\Repositories\CoverageRepository;
use SmartCircle\Data\Repositories\SchoolRepository;
use SmartCircle\Data\Repositories\TextbookRepository;
use SmartCircle\Support\Subjects;

/**
 * What a school has taught (coverage) and its textbooks with page references.
 * Used by partners for their own school and by the tutor for any school
 * (when a teacher reports by phone).
 */
final class SchoolService
{
    public const COVERAGE = [
        'covered' => 'Taught',
        'covering' => 'Teaching',
        'next' => 'Next',
    ];

    public function __construct(
        private readonly SchoolRepository $schools,
        private readonly CoverageRepository $coverage,
        private readonly TextbookRepository $textbooks,
        private readonly TopicService $topics,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function school(int $id): ?array
    {
        return $this->schools->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function allSchools(): array
    {
        return $this->schools->allWithCounts();
    }

    /**
     * Topic tree for one form/subject with this school's status on each topic.
     * @return list<array<string, mixed>>
     */
    public function coverageTree(int $schoolId, int $form, string $subject): array
    {
        $statuses = $this->coverage->forSchool($schoolId, $form, $subject);
        $tree = $this->topics->tree($form, $subject);
        foreach ($tree as &$t) {
            $t['coverage'] = $statuses[(int) $t['id']] ?? '';
            foreach ($t['children'] as &$c) {
                $c['coverage'] = $statuses[(int) $c['id']] ?? '';
            }
        }

        return $tree;
    }

    /**
     * Save a coverage form. Only topics of this form/subject are touched.
     * @param array<string, mixed> $posted topic id => status
     * @return int topics marked taught / teaching / next
     */
    public function saveCoverage(int $schoolId, int $form, string $subject, array $posted, int $reportedBy): int
    {
        $allowed = [];
        foreach ($this->topics->tree($form, $subject) as $t) {
            $allowed[(int) $t['id']] = true;
            foreach ($t['children'] as $c) {
                $allowed[(int) $c['id']] = true;
            }
        }
        $clean = self::cleanCoverage($posted, $allowed);
        $this->coverage->save($schoolId, $clean, $reportedBy);

        return count(array_filter($clean, static fn (string $s): bool => $s !== ''));
    }

    /**
     * @param array<string, mixed> $posted
     * @param array<int, true> $allowed topic ids
     * @return array<int, string> topic id => status ('' = not yet)
     */
    public static function cleanCoverage(array $posted, array $allowed): array
    {
        $clean = [];
        foreach ($posted as $id => $status) {
            $id = (int) $id;
            if (!is_string($status) || !isset($allowed[$id]) || ($status !== '' && !isset(self::COVERAGE[$status]))) {
                continue;
            }
            $clean[$id] = $status;
        }

        return $clean;
    }

    /** @return array<string, array{covered: int, total: int}> */
    public function progress(int $schoolId): array
    {
        return $this->coverage->progress($schoolId);
    }

    /** @return array<int, int> */
    public function studentsByForm(int $schoolId): array
    {
        return $this->schools->studentsByForm($schoolId);
    }

    /** @param list<string> $subjects @return list<array<string, mixed>> */
    public function topRequests(int $schoolId, array $subjects): array
    {
        return $this->schools->topRequests($schoolId, $subjects);
    }

    /* ---------- Textbooks ---------- */

    /** @return list<array<string, mixed>> */
    public function textbooks(int $schoolId): array
    {
        return $this->textbooks->forSchool($schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function knownTextbooks(): array
    {
        return $this->textbooks->known();
    }

    /**
     * @param array<string, string> $input subject, form, title, edition, publisher — or known_id
     * @return array<string, string> errors
     */
    public function addTextbook(int $schoolId, array $input): array
    {
        $knownId = (int) ($input['known_id'] ?? 0);
        if ($knownId > 0) {
            if ($this->textbooks->find($knownId) === null) {
                return ['known_id' => 'Choose a book from the list.'];
            }
            $this->textbooks->link($schoolId, $knownId);

            return [];
        }
        $subject = (string) ($input['subject'] ?? '');
        $form = (int) ($input['form'] ?? 0);
        $title = self::oneLine((string) ($input['title'] ?? ''), 200);
        $errors = [];
        if (!isset(Subjects::NAMES[$subject])) {
            $errors['subject'] = 'Choose the subject.';
        }
        if ($form < 1 || $form > 4) {
            $errors['form'] = 'Choose the form.';
        }
        if (mb_strlen($title) < 3) {
            $errors['title'] = 'Type the book title as printed on the cover.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $id = $this->textbooks->findOrCreate(
            $subject,
            $form,
            $title,
            self::oneLine((string) ($input['edition'] ?? ''), 60),
            self::oneLine((string) ($input['publisher'] ?? ''), 120)
        );
        $this->textbooks->link($schoolId, $id);

        return [];
    }

    public function removeTextbook(int $schoolId, int $textbookId): void
    {
        $this->textbooks->unlink($schoolId, $textbookId);
    }

    /** @return array<string, mixed>|null a textbook the school uses */
    public function schoolTextbook(int $schoolId, int $textbookId): ?array
    {
        return $this->textbooks->schoolUses($schoolId, $textbookId) ? $this->textbooks->find($textbookId) : null;
    }

    /**
     * The topics of the book's form/subject with their current chapter/pages.
     * @param array<string, mixed> $book
     * @return list<array<string, mixed>>
     */
    public function refsTree(array $book): array
    {
        $refs = $this->textbooks->refs((int) $book['id']);
        $tree = $this->topics->tree((int) $book['form'], (string) $book['subject']);
        $blank = ['chapter' => '', 'pages' => ''];
        foreach ($tree as &$t) {
            $t['ref'] = $refs[(int) $t['id']] ?? $blank;
            foreach ($t['children'] as &$c) {
                $c['ref'] = $refs[(int) $c['id']] ?? $blank;
            }
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $book
     * @param array<string, mixed> $chapters topic id => chapter
     * @param array<string, mixed> $pages topic id => pages
     */
    public function saveRefs(array $book, array $chapters, array $pages): int
    {
        $allowed = [];
        foreach ($this->topics->tree((int) $book['form'], (string) $book['subject']) as $t) {
            $allowed[(int) $t['id']] = true;
            foreach ($t['children'] as $c) {
                $allowed[(int) $c['id']] = true;
            }
        }
        $refs = [];
        foreach (array_keys($allowed) as $id) {
            if (!array_key_exists($id, $chapters) && !array_key_exists($id, $pages)) {
                continue;
            }
            $refs[$id] = [
                'chapter' => self::oneLine(is_string($chapters[$id] ?? null) ? $chapters[$id] : '', 60),
                'pages' => self::oneLine(is_string($pages[$id] ?? null) ? $pages[$id] : '', 60),
            ];
        }
        $this->textbooks->saveRefs((int) $book['id'], $refs);

        return count(array_filter($refs, static fn (array $r): bool => $r['chapter'] !== '' || $r['pages'] !== ''));
    }

    private static function oneLine(string $s, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $s)), 0, $max);
    }
}
