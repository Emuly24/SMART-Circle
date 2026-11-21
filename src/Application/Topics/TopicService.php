<?php

declare(strict_types=1);

namespace SmartCircle\Application\Topics;

use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Data\Repositories\TextbookRepository;
use SmartCircle\Data\Repositories\TopicRepository;
use SmartCircle\Support\Subjects;

/**
 * The topic catalogue: what each form studies, who may see which subjects,
 * and the tutor's tools to correct or extend the preloaded syllabus.
 */
final class TopicService
{
    public function __construct(
        private readonly TopicRepository $topics,
        private readonly StudentRepository $students,
        private readonly ?TextbookRepository $textbooks = null,
    ) {
    }

    /** What the student's school reported for a topic: covered | covering | next | null */
    public function schoolCoverage(?array $profile, int $topicId): ?string
    {
        return !empty($profile['school_id']) ? $this->topics->coverage((int) $profile['school_id'], $topicId) : null;
    }

    /**
     * Textbook chapters/pages for a topic; books used at the student's school first.
     * @return list<array<string, mixed>>
     */
    public function textbookRefs(int $topicId, ?array $profile): array
    {
        return $this->textbooks?->forTopic($topicId, !empty($profile['school_id']) ? (int) $profile['school_id'] : null) ?? [];
    }

    /** @return array<string, mixed>|null the student's profile (form, track, school) */
    public function studentProfile(int $userId): ?array
    {
        return $this->students->profile($userId);
    }

    /**
     * Subjects a viewer may browse. Students: their track's subjects. Tutor: all.
     * @param array<string, mixed>|null $profile student profile (null for the tutor)
     * @return list<string>
     */
    public static function subjectsFor(?array $profile): array
    {
        return $profile === null ? array_keys(Subjects::NAMES) : Subjects::forTrack((string) $profile['track']);
    }

    /** @param array<string, mixed>|null $profile */
    public static function mayBrowse(?array $profile, int $form, string $subject): bool
    {
        if ($form < 1 || $form > 4 || !isset(Subjects::NAMES[$subject])) {
            return false;
        }
        if ($profile === null) {
            return true;
        }

        return (int) $profile['form'] === $form && in_array($subject, self::subjectsFor($profile), true);
    }

    /**
     * Topics as a tree: top-level topics, each with 'children'.
     * @return list<array<string, mixed>>
     */
    public function tree(int $form, string $subject, bool $includeHidden = false): array
    {
        return self::buildTree($this->topics->forFormSubject($form, $subject, $includeHidden));
    }

    /**
     * @param list<array<string, mixed>> $rows flat rows (parents and children, any order)
     * @return list<array<string, mixed>>
     */
    public static function buildTree(array $rows): array
    {
        $parents = [];
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $parents[(int) $row['id']] = $row + ['children' => []];
            } else {
                $children[] = $row;
            }
        }
        foreach ($children as $child) {
            if (isset($parents[(int) $child['parent_id']])) {
                $parents[(int) $child['parent_id']]['children'][] = $child;
            }
        }
        $byOrder = static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']];
        $parents = array_values($parents);
        usort($parents, $byOrder);
        foreach ($parents as &$p) {
            usort($p['children'], $byOrder);
        }

        return $parents;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->topics->find($id);
    }

    /** @return list<array<string, mixed>> */
    public function children(int $id): array
    {
        return $this->topics->children($id);
    }

    /** @return list<array<string, mixed>> */
    public function lessons(int $id): array
    {
        return $this->topics->lessons($id);
    }

    /** @return list<array{id: int, form: int, subject: string, label: string}> */
    public function options(): array
    {
        return $this->topics->options();
    }

    /**
     * Tutor: add one topic (or subtopic).
     * @return array<string, string> errors
     */
    public function add(int $form, string $subject, ?int $parentId, string $title, string $unit = ''): array
    {
        $title = self::oneLine($title, 200);
        $unit = self::oneLine($unit, 150);
        if (!isset(Subjects::NAMES[$subject]) || $form < 1 || $form > 4) {
            return ['title' => 'Choose a form and subject.'];
        }
        if (mb_strlen($title) < 2) {
            return ['title' => 'Type the topic title.'];
        }
        if ($parentId !== null) {
            $parent = $this->topics->find($parentId);
            if ($parent === null || $parent['parent_id'] !== null || (int) $parent['form'] !== $form || $parent['subject'] !== $subject) {
                return ['parent_id' => 'Choose a main topic from this form and subject.'];
            }
        }
        $this->topics->create($form, $subject, $parentId, $unit === '' ? null : $unit, $title, $this->topics->nextSortOrder($form, $subject, $parentId));

        return [];
    }

    /**
     * Tutor: rename, reorder, hide or show a topic.
     * @param array<string, string> $input
     * @return array<string, string> errors
     */
    public function update(int $id, array $input): array
    {
        $title = self::oneLine($input['title'] ?? '', 200);
        if (mb_strlen($title) < 2) {
            return ['title' => 'Type the topic title.'];
        }
        $unit = self::oneLine($input['unit'] ?? '', 150);
        $objectives = trim(str_replace("\r\n", "\n", $input['objectives'] ?? ''));
        $this->topics->update(
            $id,
            $unit === '' ? null : $unit,
            $title,
            $objectives === '' ? null : mb_substr($objectives, 0, 3000),
            max(-32000, min(32000, (int) ($input['sort_order'] ?? 0))),
            ($input['is_active'] ?? '') === '1'
        );

        return [];
    }

    /**
     * Tutor: paste a list (one topic per line, "Unit 2: Title (sub, sub)") to add many at once.
     * @return int topics added (subtopics included)
     */
    public function import(int $form, string $subject, string $text): int
    {
        if ($form < 1 || $form > 4 || !isset(Subjects::NAMES[$subject])) {
            return 0;
        }
        $added = 0;
        foreach (TopicLineParser::parseList($text) as $topic) {
            $parentId = $this->topics->create(
                $form,
                $subject,
                null,
                $topic['unit'],
                mb_substr($topic['title'], 0, 200),
                $this->topics->nextSortOrder($form, $subject, null)
            );
            $added++;
            foreach ($topic['subtopics'] as $i => $sub) {
                $this->topics->create($form, $subject, $parentId, null, mb_substr($sub, 0, 200), ($i + 1) * 10);
                $added++;
            }
        }

        return $added;
    }

    private static function oneLine(string $s, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $s)), 0, $max);
    }
}
