<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The outgoing email queue and the lazy job clock. */
final class MailRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $m */
    public function queue(array $m): int
    {
        return $this->db->insert(
            'INSERT INTO mail_queue (to_email, to_name, subject, html_body, text_body, template_key, related_type, related_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$m['to_email'], $m['to_name'], $m['subject'], $m['html'], $m['text'], $m['template'], $m['related_type'], $m['related_id'], $m['created_by']]
        );
    }

    /** @return list<array<string, mixed>> */
    public function due(int $limit): array
    {
        return $this->db->all(
            "SELECT * FROM mail_queue WHERE status = 'queued' AND (next_try_at IS NULL OR next_try_at <= NOW()) ORDER BY id LIMIT " . max(1, min(50, $limit))
        );
    }

    public function countDue(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM mail_queue WHERE status = 'queued' AND (next_try_at IS NULL OR next_try_at <= NOW())");
    }

    public function markSent(int $id): void
    {
        $this->db->run("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, last_error = NULL WHERE id = ?", [$id]);
    }

    public function markFailed(int $id, string $error, ?int $retryInMinutes): void
    {
        $this->db->run(
            "UPDATE mail_queue SET attempts = attempts + 1, last_error = ?, status = IF(? IS NULL, 'failed', 'queued'),
                    next_try_at = IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE) WHERE id = ?",
            [mb_substr($error, 0, 500), $retryInMinutes, $retryInMinutes, (int) $retryInMinutes, $id]
        );
    }

    public function retryFailed(): int
    {
        return $this->db->run("UPDATE mail_queue SET status = 'queued', attempts = 0, next_try_at = NULL WHERE status = 'failed'")->rowCount();
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $out = ['queued' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($this->db->all('SELECT status, COUNT(*) AS n FROM mail_queue GROUP BY status') as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        $out['sent_today'] = (int) $this->db->scalar("SELECT COUNT(*) FROM mail_queue WHERE status = 'sent' AND sent_at >= CURDATE()");

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 30): array
    {
        return $this->db->all('SELECT id, to_email, to_name, subject, template_key, status, attempts, last_error, created_at, sent_at, next_try_at FROM mail_queue ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)));
    }

    /** @return list<array{id: int, full_name: string, email: string}> users with an email who want notifications by email */
    public function notifiable(array $userIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if ($ids === []) {
            return [];
        }

        return $this->db->all(
            "SELECT id, full_name, email FROM users WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
             AND status = 'active' AND is_practice = 0 AND email IS NOT NULL AND email <> '' AND email_notifications = 1",
            $ids
        );
    }

    /** True (and the clock moves on) when the job is due; one request wins. */
    public function claimJob(string $name): bool
    {
        return $this->db->run(
            'UPDATE scheduled_jobs SET last_run_at = NOW() WHERE name = ? AND (last_run_at IS NULL OR last_run_at <= NOW() - INTERVAL interval_minutes MINUTE)',
            [$name]
        )->rowCount() === 1;
    }

    public function touchJob(string $name): void
    {
        $this->db->run('UPDATE scheduled_jobs SET last_run_at = NOW() WHERE name = ?', [$name]);
    }

    public function jobResult(string $name, string $result): void
    {
        $this->db->run('UPDATE scheduled_jobs SET last_result = ? WHERE name = ?', [mb_substr($result, 0, 500), $name]);
    }

    /** @return array<string, mixed>|null */
    public function job(string $name): ?array
    {
        return $this->db->one('SELECT * FROM scheduled_jobs WHERE name = ?', [$name]);
    }

    /**
     * People in a group who can get an email the tutor writes: an address, an active account (not practice)
     * and email turned on in their profile. Guardians have no account: those with an email address on file.
     * @return list<array{email: string, name: string, title: ?string, student_name: ?string}>
     */
    public function audience(string $group, ?int $form = null): array
    {
        $formSql = $form !== null ? ' AND sp.form = ?' : '';
        $params = $form !== null ? [$form] : [];
        $users = "u.status = 'active' AND u.is_practice = 0 AND u.email IS NOT NULL AND u.email <> '' AND u.email_notifications = 1";
        $rows = match ($group) {
            'students' => $this->db->all(
                "SELECT u.email, u.full_name AS name, NULL AS title, NULL AS student_name FROM users u JOIN student_profiles sp ON sp.user_id = u.id
                 WHERE u.role = 'student' AND sp.status = 'active' AND $users$formSql ORDER BY u.full_name",
                $params
            ),
            'guardians' => $this->db->all(
                "SELECT sp.guardian_email AS email, COALESCE(sp.guardian_name, '') AS name, NULL AS title, u.full_name AS student_name FROM student_profiles sp JOIN users u ON u.id = sp.user_id
                 WHERE sp.status = 'active' AND u.status = 'active' AND u.is_practice = 0 AND sp.guardian_email IS NOT NULL AND sp.guardian_email <> ''$formSql
                 ORDER BY sp.guardian_name",
                $params
            ),
            'partner', 'team', 'alumni' => $this->db->all(
                "SELECT u.email, u.full_name AS name, u.honorific AS title, NULL AS student_name FROM users u WHERE u.role = ? AND $users ORDER BY u.full_name",
                [$group]
            ),
            default => [],
        };
        $out = [];
        foreach ($rows as $r) {
            $key = strtolower(trim((string) $r['email']));
            if ($key !== '' && !isset($out[$key])) {
                $out[$key] = ['email' => trim((string) $r['email']), 'name' => (string) $r['name'], 'title' => $r['title'] !== null ? (string) $r['title'] : null, 'student_name' => $r['student_name'] !== null ? (string) $r['student_name'] : null];
            }
        }

        return array_values($out);
    }
}
