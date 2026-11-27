<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** What a partner teacher has given to SMART Circle, counted from the records. */
final class ContributionStatsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, int> */
    public function counts(int $userId): array
    {
        $q = fn (string $sql): int => (int) $this->db->scalar($sql, [$userId]);

        return [
            'notes_checked' => $q("SELECT COUNT(DISTINCT note_id) FROM note_reviews WHERE reviewer_id = ?"),
            'lessons_checked' => $q("SELECT COUNT(DISTINCT lesson_id) FROM lesson_reviews WHERE reviewer_id = ?"),
            'questions_added' => $q("SELECT COUNT(*) FROM question_suggestions WHERE partner_id = ? AND status = 'accepted'"),
            'guest_lessons' => $q("SELECT COUNT(*) FROM lessons WHERE created_by = ? AND status = 'published'"),
            'coverage_reports' => $q("SELECT COUNT(*) FROM topic_coverage WHERE reported_by = ?"),
            'resources' => $q("SELECT COUNT(*) FROM school_resources WHERE user_id = ?"),
            'calendar_checks' => $q("SELECT COUNT(*) FROM calendar_confirmations WHERE user_id = ?"),
        ];
    }
}
