<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

final class IntakeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null the active intake (open or not), newest first */
    public function current(): ?array
    {
        return $this->db->one('SELECT * FROM intakes WHERE is_active = 1 ORDER BY opens_at DESC, id DESC LIMIT 1');
    }

    /** @return array<string, mixed>|null the active intake if it is accepting applications right now */
    public function open(): ?array
    {
        return $this->db->one(
            'SELECT * FROM intakes WHERE is_active = 1 AND opens_at <= NOW() AND (closes_at IS NULL OR closes_at > NOW())
             ORDER BY opens_at DESC, id DESC LIMIT 1'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM intakes WHERE id = ?', [$id]);
    }

    public function update(int $id, string $name, ?string $closesAt, int $seatsPerGroup, bool $active, ?string $seatPlan = null): void
    {
        $this->db->run(
            'UPDATE intakes SET name = ?, closes_at = ?, seats_per_group = ?, is_active = ?, seat_plan = ? WHERE id = ?',
            [$name, $closesAt, $seatsPerGroup, $active ? 1 : 0, $seatPlan, $id]
        );
    }

    public function create(string $name, int $seatsPerGroup): int
    {
        // Only one active intake at a time.
        $this->db->run('UPDATE intakes SET is_active = 0');

        return $this->db->insert(
            'INSERT INTO intakes (name, opens_at, seats_per_group, is_active) VALUES (?, NOW(), ?, 1)',
            [$name, $seatsPerGroup]
        );
    }
}
