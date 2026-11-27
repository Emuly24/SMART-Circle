<?php

declare(strict_types=1);

namespace SmartCircle\Application\Learning;

use SmartCircle\Application\Assessments\Marker;
use SmartCircle\Application\Topics\AccessService;
use SmartCircle\Data\Repositories\LearningRepository;
use SmartCircle\Data\Repositories\StudentRepository;
use SmartCircle\Support\Subjects;

/**
 * Quick 5: five short, auto-marked questions a week on topics the student has already met, because
 * remembering a little, often, is what makes learning stick. Only topics open to the student
 * (the honesty rule), never a question they answered in the last two weeks.
 */
final class Quick5Service
{
    public const SIZE = 5;
    public const REPEAT_AFTER_DAYS = 14;

    public function __construct(
        private readonly LearningRepository $repo,
        private readonly StudentRepository $students,
        private readonly AccessService $access,
    ) {
    }

    public static function week(?int $time = null): string
    {
        return date('o-\WW', $time ?? time());
    }

    /**
     * Picks questions: open topics only, not answered recently, a fair spread of subjects. Pure.
     * The same student gets the same set all week (stable seed), so refreshing does not reshuffle.
     * @param list<array<string, mixed>> $candidates
     * @param \Closure(int): bool $open topic id => open to this student
     * @param list<int> $recent
     * @return list<array<string, mixed>>
     */
    public static function pick(array $candidates, \Closure $open, array $recent, string $seed, int $size = self::SIZE): array
    {
        $pool = array_values(array_filter($candidates, static fn (array $q): bool => !in_array((int) $q['id'], $recent, true) && $open((int) $q['topic_id'])));
        usort($pool, static fn (array $a, array $b): int => strcmp(md5($seed . $a['id']), md5($seed . $b['id'])));
        // Round-robin across subjects so one subject does not take all five.
        $bySubject = [];
        foreach ($pool as $q) {
            $bySubject[(string) $q['subject']][] = $q;
        }
        $out = [];
        while (count($out) < $size && $bySubject !== []) {
            foreach (array_keys($bySubject) as $s) {
                $out[] = array_shift($bySubject[$s]);
                if ($bySubject[$s] === []) {
                    unset($bySubject[$s]);
                }
                if (count($out) === $size) {
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null the week's questions (without answers), streak and accuracy */
    public function forStudent(int $userId): ?array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return null;
        }
        $questions = $this->questions($userId, $profile, $decide);
        $weeks = $this->repo->weeks($userId);

        return [
            'questions' => array_map(static fn (array $q): array => self::publicView($q), $questions),
            'done_this_week' => ($weeks[self::week()] ?? 0) >= self::SIZE,
            'streak' => self::streak($weeks, time()),
            'accuracy' => $this->repo->accuracy($userId, 30),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function questions(int $userId, array $profile, \Closure $decide): array
    {
        $candidates = $this->repo->candidateQuestions(max(array_map('intval', $profile['forms'] ?? [(int) $profile['form']])), Subjects::forTrack((string) $profile['track']));

        return self::pick($candidates, static fn (int $topicId): bool => $decide($topicId)['open'], $this->repo->recentQuestionIds($userId, self::REPEAT_AFTER_DAYS),
            $userId . '|' . self::week() . '|' . count($this->repo->weeks($userId)));
    }

    /**
     * Marks the answers to this week's set and records them.
     * @param array<int|string, string> $answers question id => answer
     * @return list<array<string, mixed>>|null each with correct, your answer, the right answer
     */
    public function check(int $userId, array $answers): ?array
    {
        $profile = $this->access->withRevision($this->students->profile($userId));
        $decide = $this->access->forStudent($userId);
        if ($profile === null || $decide === null) {
            return null;
        }
        $results = [];
        foreach ($this->questions($userId, $profile, $decide) as $q) {
            $data = (array) json_decode((string) $q['data'], true);
            $given = trim((string) ($answers[(string) $q['id']] ?? $answers[(int) $q['id']] ?? ''));
            $score = Marker::score((string) $q['type'], $data, $given, 1.0);
            $correct = $score !== null && $score >= 0.999;
            $this->repo->record($userId, (int) $q['id'], $correct, self::week());
            $results[] = self::publicView($q) + ['correct' => $correct, 'given' => self::shown($q, $data, $given), 'right' => self::rightAnswer((string) $q['type'], $data)];
        }

        return $results;
    }

    /** Weeks in a row (ending this week or last week) with a full Quick 5. Pure. @param array<string, int> $weeks */
    public static function streak(array $weeks, int $now): int
    {
        $n = 0;
        $t = $now;
        if (($weeks[self::week($t)] ?? 0) < self::SIZE) {
            $t -= 7 * 86400; // this week is not done yet: count from last week
        }
        while (($weeks[self::week($t)] ?? 0) >= self::SIZE) {
            $n++;
            $t -= 7 * 86400;
        }

        return $n;
    }

    /** The right answer, in words. Pure. @param array<string, mixed> $data */
    public static function rightAnswer(string $type, array $data): string
    {
        return match ($type) {
            'mcq' => (string) (((array) ($data['options'] ?? []))[(int) ($data['correct'] ?? 0)] ?? ''),
            'numeric' => rtrim(rtrim(number_format((float) ($data['answer'] ?? 0), 6, '.', ''), '0'), '.') . (($data['unit'] ?? '') !== '' ? ' ' . $data['unit'] : ''),
            'grammar_fill' => (string) (((array) ($data['accepted'] ?? []))[0] ?? ''),
            default => (string) ($data['model_answer'] ?? ''),
        };
    }

    /** @param array<string, mixed> $q */
    private static function shown(array $q, array $data, string $given): string
    {
        return $q['type'] === 'mcq' && $given !== '' ? (string) (((array) ($data['options'] ?? []))[(int) $given] ?? $given) : $given;
    }

    /** A question for the page: never the answer. @param array<string, mixed> $q @return array<string, mixed> */
    private static function publicView(array $q): array
    {
        $data = (array) json_decode((string) $q['data'], true);

        return [
            'id' => (int) $q['id'],
            'type' => (string) $q['type'],
            'prompt' => (string) $q['prompt'],
            'subject' => (string) $q['subject'],
            'max_marks' => 1,
            'data' => array_intersect_key($data, array_flip(['options', 'unit'])),
        ];
    }
}
