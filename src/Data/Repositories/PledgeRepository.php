<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Signed SMART Circle pledges (one per signing; renewed each term later). */
final class PledgeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, string> $statements */
    public function create(int $userId, string $version, array $statements, string $signedName, ?string $guardian, string $ip): int
    {
        return $this->db->insert(
            'INSERT INTO pledges (user_id, version, statements, signed_name, guardian_name, ip) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $version, json_encode($statements, JSON_UNESCAPED_UNICODE), $signedName, $guardian, $ip]
        );
    }

    public function setDocument(int $id, string $code): void
    {
        $this->db->run('UPDATE pledges SET document_code = ? WHERE id = ?', [$code, $id]);
    }

    /** @return array<string, mixed>|null */
    public function latest(int $userId): ?array
    {
        return $this->db->one('SELECT * FROM pledges WHERE user_id = ? ORDER BY accepted_at DESC, id DESC LIMIT 1', [$userId]);
    }
}
