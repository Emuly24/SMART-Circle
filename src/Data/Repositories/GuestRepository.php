<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Partner teachers who record lessons as guests. */
final class GuestRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array{subjects: list<string>}|null an active guest teacher, or null */
    public function guest(int $userId): ?array
    {
        $r = $this->db->one(
            "SELECT pp.subjects FROM partner_profiles pp JOIN users u ON u.id = pp.user_id WHERE pp.user_id = ? AND pp.guest_teacher = 1 AND u.status = 'active'",
            [$userId]
        );

        return $r === null ? null : ['subjects' => array_values(array_filter(explode(',', (string) $r['subjects'])))];
    }

    public function setGuest(int $userId, bool $on): void
    {
        $this->db->run('UPDATE partner_profiles SET guest_teacher = ?, guest_since = IF(?, COALESCE(guest_since, NOW()), NULL) WHERE user_id = ?', [$on ? 1 : 0, $on ? 1 : 0, $userId]);
    }

    /** @return array{name: string, school: ?string}|null the guest teacher who recorded a lesson */
    public function credit(int $lessonId): ?array
    {
        $r = $this->db->one(
            "SELECT u.full_name, s.name AS school FROM lessons l JOIN users u ON u.id = l.created_by AND u.role = 'partner'
             LEFT JOIN partner_profiles pp ON pp.user_id = u.id LEFT JOIN schools s ON s.id = pp.school_id WHERE l.id = ?",
            [$lessonId]
        );

        return $r === null ? null : ['name' => (string) $r['full_name'], 'school' => $r['school'] !== null ? (string) $r['school'] : null];
    }

    public function submit(int $lessonId): void
    {
        $this->db->run('UPDATE lessons SET guest_submitted_at = NOW() WHERE id = ?', [$lessonId]);
    }

    /** @return array<int, array{name: string, submitted_at: ?string}> lesson id => guest teacher, for lessons recorded by guests */
    public function guestLessons(): array
    {
        $out = [];
        foreach ($this->db->all("SELECT l.id, l.guest_submitted_at, u.full_name FROM lessons l JOIN users u ON u.id = l.created_by AND u.role = 'partner'") as $r) {
            $out[(int) $r['id']] = ['name' => (string) $r['full_name'], 'submitted_at' => $r['guest_submitted_at']];
        }

        return $out;
    }

    public function creatorOf(int $lessonId): ?int
    {
        $v = $this->db->scalar("SELECT l.created_by FROM lessons l JOIN users u ON u.id = l.created_by AND u.role = 'partner' WHERE l.id = ?", [$lessonId]);

        return $v === null || $v === false ? null : (int) $v;
    }
}
