<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Office-hour slots the tutor offers, and who booked them. */
final class OfficeHoursRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $d */
    public function create(array $d, int $tutorId): int
    {
        return $this->db->insert(
            'INSERT INTO office_hours (starts_at, ends_at, mode, capacity, audience, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['starts_at'], $d['ends_at'], $d['mode'], $d['capacity'], $d['audience'], $d['note'], $tutorId]
        );
    }

    /** @return array<string, mixed>|null */
    public function slot(int $id): ?array
    {
        return $this->db->one(
            "SELECT h.*, (SELECT COUNT(*) FROM office_hour_bookings b WHERE b.slot_id = h.id AND b.status <> 'cancelled') AS booked
             FROM office_hours h WHERE h.id = ?",
            [$id]
        );
    }

    /** @return list<array<string, mixed>> slots from a moment on (with booking counts) */
    public function upcoming(string $from, bool $includeCancelled = false): array
    {
        return $this->db->all(
            "SELECT h.*, (SELECT COUNT(*) FROM office_hour_bookings b WHERE b.slot_id = h.id AND b.status <> 'cancelled') AS booked
             FROM office_hours h WHERE h.ends_at >= ?" . ($includeCancelled ? '' : " AND h.status = 'open'") . ' ORDER BY h.starts_at LIMIT 100',
            [$from]
        );
    }

    /** @return list<array<string, mixed>> recent past slots */
    public function past(string $before): array
    {
        return $this->db->all(
            "SELECT h.*, (SELECT COUNT(*) FROM office_hour_bookings b WHERE b.slot_id = h.id AND b.status <> 'cancelled') AS booked
             FROM office_hours h WHERE h.ends_at < ? ORDER BY h.starts_at DESC LIMIT 30",
            [$before]
        );
    }

    public function cancelSlot(int $id): void
    {
        $this->db->run("UPDATE office_hours SET status = 'cancelled' WHERE id = ?", [$id]);
    }

    /** @return list<array<string, mixed>> bookings on a slot with who made them */
    public function bookings(int $slotId): array
    {
        return $this->db->all(
            'SELECT b.*, u.full_name, u.phone, u.role FROM office_hour_bookings b JOIN users u ON u.id = b.user_id WHERE b.slot_id = ? ORDER BY b.created_at, b.id',
            [$slotId]
        );
    }

    /** @return array<string, mixed>|null */
    public function booking(int $slotId, int $userId): ?array
    {
        return $this->db->one('SELECT * FROM office_hour_bookings WHERE slot_id = ? AND user_id = ?', [$slotId, $userId]);
    }

    /** @return array<string, mixed>|null */
    public function bookingById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM office_hour_bookings WHERE id = ?', [$id]);
    }

    /** @return array<int, array<string, mixed>> slot id => this user's booking */
    public function bookingsOf(int $userId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM office_hour_bookings WHERE user_id = ?', [$userId]) as $b) {
            $out[(int) $b['slot_id']] = $b;
        }

        return $out;
    }

    /** Books (or re-books after cancelling); false if the slot filled up meanwhile. */
    public function book(int $slotId, int $userId, string $question, int $capacity): bool
    {
        return $this->db->transaction(function (Database $db) use ($slotId, $userId, $question, $capacity): bool {
            $db->one('SELECT id FROM office_hours WHERE id = ? FOR UPDATE', [$slotId]);
            $taken = (int) $db->scalar("SELECT COUNT(*) FROM office_hour_bookings WHERE slot_id = ? AND status <> 'cancelled' AND user_id <> ?", [$slotId, $userId]);
            if ($taken >= $capacity) {
                return false;
            }
            $db->run(
                "INSERT INTO office_hour_bookings (slot_id, user_id, question) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE question = VALUES(question), status = 'booked'",
                [$slotId, $userId, $question]
            );

            return true;
        });
    }

    public function setBookingStatus(int $id, string $status): void
    {
        $this->db->run('UPDATE office_hour_bookings SET status = ? WHERE id = ?', [$status, $id]);
    }
}
