<?php

declare(strict_types=1);

namespace SmartCircle\Application\Community;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\ProgressRepository;

/**
 * Badges for real effort (never for marks, so every student can earn them), and exit tickets: after a
 * lesson, a student says how well they understood it, one real-life use, and what is still unclear.
 */
final class ProgressService
{
    /** code => [name, how to earn it, figure, at least] */
    public const BADGES = [
        'first_lesson' => ['First lesson', 'Watch your first lesson', 'lessons', 1],
        'ten_lessons' => ['Ten lessons', 'Watch 10 different lessons', 'lessons', 10],
        'steady_week' => ['Steady week', 'Study on 3 different days in one week', 'study_days_7', 3],
        'first_work' => ['Hands in work', 'Hand in your first exercise or exam', 'work', 1],
        'reflector' => ['Reflector', 'Fill in 5 exit tickets', 'exit_tickets', 5],
        'curious' => ['Curious mind', 'Ask your first question under a lesson', 'questions_asked', 1],
        'helper' => ['Helper', 'Give an answer the tutor approves or a classmate thanks you for', 'helped', 1],
        'super_helper' => ['Super helper', 'Help 5 times', 'helped', 5],
        'problem_solver' => ['Problem solver', 'Answer 3 weekly challenges', 'challenges', 3],
        'spotter' => ['Spotter', 'Get a "Spotted in real life" photo on the wall', 'wall_posts', 1],
        'featured' => ['Featured', 'Have a wall photo featured', 'wall_featured', 1],
    ];

    public function __construct(
        private readonly ProgressRepository $repo,
        private readonly NotificationService $notifications,
    ) {
    }

    /** Awards any badges now earned (and says so). @return array<string, string> all badges: code => earned_at */
    public function refresh(int $userId): array
    {
        $have = $this->repo->badges($userId);
        $new = self::earned($this->repo->figures($userId), array_keys($have));
        foreach ($new as $code) {
            if ($this->repo->award($userId, $code)) {
                $this->notifications->notify([$userId], 'badge', 'New badge: ' . self::BADGES[$code][0], self::BADGES[$code][1] . '. Well done!', '/community');
            }
        }

        return $new === [] ? $have : $this->repo->badges($userId);
    }

    /** Badges earned by these figures and not yet held. Pure. @param array<string, int> $figures @param list<string> $have @return list<string> */
    public static function earned(array $figures, array $have): array
    {
        $out = [];
        foreach (self::BADGES as $code => [, , $figure, $min]) {
            if (!in_array($code, $have, true) && ($figures[$figure] ?? 0) >= $min) {
                $out[] = $code;
            }
        }

        return $out;
    }

    /* ---------- Exit tickets ---------- */

    /** @return string error */
    public function saveTicket(int $lessonId, int $userId, int $understanding, string $use, string $unclear): string
    {
        if ($understanding < 1 || $understanding > 5) {
            return 'Choose how well you understood the lesson (1 to 5).';
        }
        $use = trim($use);
        $unclear = trim($unclear);
        if (mb_strlen($use) > 1000 || mb_strlen($unclear) > 1000) {
            return 'Please keep each answer under 1000 characters.';
        }
        $this->repo->saveTicket($lessonId, $userId, $understanding, $use === '' ? null : $use, $unclear === '' ? null : $unclear);

        return '';
    }

    /** @return array<string, mixed>|null */
    public function ticket(int $lessonId, int $userId): ?array
    {
        return $this->repo->ticket($lessonId, $userId);
    }

    /** @return array{count: int, average: ?float, unclear: list<array<string, mixed>>, uses: list<array<string, mixed>>} */
    public function summary(int $lessonId): array
    {
        $t = $this->repo->tickets($lessonId);

        return [
            'count' => count($t),
            'average' => $t === [] ? null : round(array_sum(array_map(static fn (array $x): int => (int) $x['understanding'], $t)) / count($t), 1),
            'unclear' => array_values(array_filter($t, static fn (array $x): bool => $x['still_unclear'] !== null)),
            'uses' => array_values(array_filter($t, static fn (array $x): bool => $x['real_life_use'] !== null)),
        ];
    }
}
