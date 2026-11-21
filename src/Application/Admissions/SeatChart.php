<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/**
 * The 24 seats: Forms 1-4 x girls/boys x seats_per_group (default 3).
 * An empty girls' seat stays a girls' seat (and the same for boys).
 */
final class SeatChart
{
    /** @var array<int, array<string, array{seats: int, admitted: int, waitlisted: int, pending: int}>> */
    private array $cells = [];

    /** @param list<array{form: int, gender: string, status: string, n: int}> $counts */
    public function __construct(public readonly int $seatsPerGroup, array $counts)
    {
        foreach ([1, 2, 3, 4] as $form) {
            foreach (['female', 'male'] as $gender) {
                $this->cells[$form][$gender] = ['seats' => $seatsPerGroup, 'admitted' => 0, 'waitlisted' => 0, 'pending' => 0];
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

    public function totalSeats(): int
    {
        return $this->seatsPerGroup * 8;
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
