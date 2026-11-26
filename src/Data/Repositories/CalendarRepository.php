<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Academic years, terms and calendar events (national, and each school's own dates). */
final class CalendarRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> every term with its year, in date order */
    public function terms(): array
    {
        return $this->db->all(
            'SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.year_id ORDER BY t.opens_on'
        );
    }

    /** @return array<string, mixed>|null */
    public function term(int $id): ?array
    {
        return $this->db->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.year_id WHERE t.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function years(): array
    {
        return $this->db->all('SELECT * FROM academic_years ORDER BY starts_on');
    }

    public function createYear(string $name, string $startsOn, string $endsOn): int
    {
        return $this->db->insert('INSERT INTO academic_years (name, starts_on, ends_on) VALUES (?, ?, ?)', [$name, $startsOn, $endsOn]);
    }

    public function saveTerm(int $yearId, int $number, string $opensOn, string $closesOn): void
    {
        $this->db->run(
            'INSERT INTO terms (year_id, number, opens_on, closes_on) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE opens_on = VALUES(opens_on), closes_on = VALUES(closes_on)',
            [$yearId, $number, $opensOn, $closesOn]
        );
    }

    public function updateYearDates(int $yearId): void
    {
        $this->db->run(
            'UPDATE academic_years y SET starts_on = (SELECT MIN(opens_on) FROM terms WHERE year_id = y.id),
                    ends_on = (SELECT MAX(closes_on) FROM terms WHERE year_id = y.id) WHERE y.id = ?',
            [$yearId]
        );
    }

    /**
     * National events plus the given school's own.
     * @return list<array<string, mixed>>
     */
    public function events(?int $schoolId = null, bool $allSchools = false): array
    {
        if ($allSchools) {
            return $this->db->all(
                'SELECT e.*, s.name AS school_name FROM calendar_events e LEFT JOIN schools s ON s.id = e.school_id ORDER BY e.starts_on'
            );
        }

        return $this->db->all(
            'SELECT e.*, s.name AS school_name FROM calendar_events e LEFT JOIN schools s ON s.id = e.school_id
             WHERE e.school_id IS NULL OR e.school_id = ? ORDER BY e.starts_on',
            [$schoolId ?? 0]
        );
    }

    /** @return array<string, mixed>|null */
    public function event(int $id): ?array
    {
        return $this->db->one('SELECT * FROM calendar_events WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $e */
    public function createEvent(array $e, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO calendar_events (school_id, kind, title, starts_on, ends_on, forms, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$e['school_id'], $e['kind'], $e['title'], $e['starts_on'], $e['ends_on'], $e['forms'], $createdBy]
        );
    }

    public function deleteEvent(int $id): void
    {
        $this->db->run('DELETE FROM calendar_events WHERE id = ?', [$id]);
    }
}
