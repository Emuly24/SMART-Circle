<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/**
 * The seats: Forms 1-4 x girls/boys. By default seats_per_group (3) in every cell, 24 in all;
 * the admin can set each cell separately (the intake's seat plan) to enrol more students.
 * An empty girls' seat stays a girls' seat (and the same for boys).
 */
final class SeatChart
{
    /** @var array<int, array<string, array{seats: int, admitted: int, waitlisted: int, pending: int}>> */
    private array $cells = [];

    public const MAX_PER_CELL = 30;

    /**
     * @param list<array{form: int, gender: string, status: string, n: int}> $counts
     * @param array<int|string, array<string, int|string>>|null $plan form => gender => seats
     */
    public function __construct(public readonly int $seatsPerGroup, array $counts, ?array $plan = null)
    {
        foreach ([1, 2, 3, 4] as $form) {
            foreach (['female', 'male'] as $gender) {
                $seats = isset($plan[$form][$gender]) ? max(0, min(self::MAX_PER_CELL, (int) $plan[$form][$gender])) : $seatsPerGroup;
                $this->cells[$form][$gender] = ['seats' => $seats, 'admitted' => 0, 'waitlisted' => 0, 'pending' => 0];
            }
        }
        foreach ($counts as $c) {
            if (!isset($this->cells[$c['form']][$c['gender']])) {
                continue;
            }
            $key = match ($c['status']) {
                'admitted' => 'admitted',
                'waitlisted' => 'waitlisted',
                'submitted', 'shortlisted' => 'pending',
                default => null,
            };
            if ($key !== null) {
                $this->cells[$c['form']][$c['gender']][$key] += $c['n'];
            }
        }
    }

    /** @return array{seats: int, admitted: int, waitlisted: int, pending: int, open: int} */
    public function cell(int $form, string $gender): array
    {
        $c = $this->cells[$form][$gender] ?? ['seats' => 0, 'admitted' => 0, 'waitlisted' => 0, 'pending' => 0];
        $c['open'] = max(0, $c['seats'] - $c['admitted']);

        return $c;
    }

    public function hasSeat(int $form, string $gender): bool
    {
        return $this->cell($form, $gender)['open'] > 0;
    }

    /** @param array<string, mixed> $intake seats_per_group, seat_plan (JSON or null) */
    public static function fromIntake(array $intake, array $counts): self
    {
        $plan = isset($intake['seat_plan']) && $intake['seat_plan'] !== null ? json_decode((string) $intake['seat_plan'], true) : null;

        return new self((int) $intake['seats_per_group'], $counts, is_array($plan) ? $plan : null);
    }

    /**
     * Turns the admin's seat grid into a plan. Pure.
     * @param array<int|string, array<string, mixed>> $in
     * @return array<int, array<string, int>>
     */
    public static function planFrom(array $in, int $default): array
    {
        $plan = [];
        foreach ([1, 2, 3, 4] as $form) {
            foreach (['female', 'male'] as $gender) {
                $v = $in[$form][$gender] ?? $in[(string) $form][$gender] ?? null;
                $plan[$form][$gender] = is_numeric($v) ? max(0, min(self::MAX_PER_CELL, (int) $v)) : $default;
            }
        }

        return $plan;
    }

    public function totalSeats(): int
    {
        $sum = 0;
        foreach ($this->cells as $genders) {
            foreach ($genders as $c) {
                $sum += $c['seats'];
            }
        }

        return $sum;
    }

    public function totalAdmitted(): int
    {
        $sum = 0;
        foreach ($this->cells as $genders) {
            foreach ($genders as $c) {
                $sum += $c['admitted'];
            }
        }

        return $sum;
    }

    public function totalOpen(): int
    {
        return $this->totalSeats() - $this->totalAdmitted();
    }
}
