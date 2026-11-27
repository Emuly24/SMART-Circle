<?php

declare(strict_types=1);

namespace SmartCircle\Application\Learning;

use SmartCircle\Application\Calendar\CalendarService;
use SmartCircle\Data\Repositories\LearningRepository;

/**
 * Anonymous termly feedback: three short questions about the lessons. Responses carry no name; a
 * separate list only remembers who has answered, so each student is asked once a term. With few
 * students, small groups could identify someone, so comments and per-form figures only show once
 * at least MIN_GROUP students have answered.
 */
final class FeedbackService
{
    public const MIN_GROUP = 3;
    public const PACE = ['slow' => 'Too slow', 'right' => 'About right', 'fast' => 'Too fast'];

    public function __construct(
        private readonly LearningRepository $repo,
        private readonly CalendarService $calendar,
    ) {
    }

    /** The term to ask about: the current one only. */
    public function term(): ?array
    {
        $today = $this->calendar->today();

        return $today['in_term'] ? $today['term'] : null;
    }

    public function shouldAsk(int $userId): bool
    {
        $t = $this->term();

        return $t !== null && !$this->repo->feedbackDone($userId, (int) $t['id']);
    }

    /** @return array<string, string> errors */
    public function submit(int $userId, ?int $form, array $in): array
    {
        $t = $this->term();
        if ($t === null) {
            return ['clarity' => 'Feedback opens during the term.'];
        }
        if ($this->repo->feedbackDone($userId, (int) $t['id'])) {
            return ['clarity' => 'You have already given feedback this term. Thank you!'];
        }
        $v = self::validate($in);
        if ($v['errors'] !== []) {
            return $v['errors'];
        }
        $c = $v['clean'];
        $this->repo->saveFeedback($userId, (int) $t['id'], $form, $c['clarity'], $c['pace'], $c['helpful'], $c['change']);

        return [];
    }

    /** Pure. @return array{errors: array<string, string>, clean: array<string, mixed>} */
    public static function validate(array $in): array
    {
        $errors = [];
        $clarity = (int) ($in['clarity'] ?? 0);
        if ($clarity < 1 || $clarity > 5) {
            $errors['clarity'] = 'Choose how clear the lessons are.';
        }
        $pace = (string) ($in['pace'] ?? '');
        if (!isset(self::PACE[$pace])) {
            $errors['pace'] = 'Choose the pace.';
        }
        $text = static fn (string $k): ?string => ($v = trim((string) ($in[$k] ?? ''))) === '' ? null : mb_substr($v, 0, 500);

        return ['errors' => $errors, 'clean' => ['clarity' => $clarity, 'pace' => $pace, 'helpful' => $text('helpful'), 'change' => $text('change')]];
    }

    /**
     * The summary the admin sees. Pure.
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function summarise(array $rows): array
    {
        $n = count($rows);
        $enough = $n >= self::MIN_GROUP;
        $pace = array_fill_keys(array_keys(self::PACE), 0);
        $byForm = [];
        foreach ($rows as $r) {
            $pace[(string) $r['pace']] = ($pace[(string) $r['pace']] ?? 0) + 1;
            $byForm[(int) $r['form']][] = (int) $r['clarity'];
        }
        $forms = [];
        foreach ($byForm as $f => $vals) {
            if (count($vals) >= self::MIN_GROUP) {
                $forms[$f] = ['n' => count($vals), 'clarity' => round(array_sum($vals) / count($vals), 1)];
            }
        }
        ksort($forms);

        return [
            'n' => $n,
            'enough' => $enough,
            'clarity' => $n === 0 ? null : round(array_sum(array_map(static fn (array $r): int => (int) $r['clarity'], $rows)) / $n, 1),
            'pace' => $pace,
            'forms' => $forms,
            'helpful' => $enough ? array_values(array_filter(array_column($rows, 'helpful'))) : [],
            'change' => $enough ? array_values(array_filter(array_column($rows, 'change_one'))) : [],
        ];
    }

    /** @return array<string, mixed> */
    public function report(?int $termId): array
    {
        $terms = $this->calendar->terms();
        $termId ??= $this->calendar->currentTermId();

        return ['terms' => $terms, 'term_id' => $termId, 'summary' => self::summarise($termId !== null ? $this->repo->feedback($termId) : [])];
    }
}
