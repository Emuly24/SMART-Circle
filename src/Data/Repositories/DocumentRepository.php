<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Letters and certificates issued, with the code printed on them for verification. */
final class DocumentRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(string $code, string $type, string $subjectType, int $subjectId, string $title, array $data, ?int $issuedBy): int
    {
        return $this->db->insert(
            'INSERT INTO issued_documents (code, type, subject_type, subject_id, title, data, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$code, $type, $subjectType, $subjectId, $title, json_encode($data, JSON_UNESCAPED_UNICODE), $issuedBy]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $doc = $this->db->one('SELECT * FROM issued_documents WHERE code = ?', [$code]);
        if ($doc !== null) {
            $doc['data'] = json_decode((string) $doc['data'], true) ?: [];
        }

        return $doc;
    }

    /** @return list<array<string, mixed>> */
    public function forSubject(string $subjectType, int $subjectId): array
    {
        return $this->db->all(
            'SELECT id, code, type, title, issued_at, revoked_at FROM issued_documents WHERE subject_type = ? AND subject_id = ? ORDER BY id DESC',
            [$subjectType, $subjectId]
        );
    }

    public function codeExists(string $code): bool
    {
        return $this->db->scalar('SELECT 1 FROM issued_documents WHERE code = ?', [$code]) !== null;
    }

    public function revokeForSubject(string $subjectType, int $subjectId): void
    {
        $this->db->run(
            'UPDATE issued_documents SET revoked_at = NOW() WHERE subject_type = ? AND subject_id = ? AND revoked_at IS NULL',
            [$subjectType, $subjectId]
        );
    }

    public function revokeCode(string $code): void
    {
        $this->db->run('UPDATE issued_documents SET revoked_at = NOW() WHERE code = ? AND revoked_at IS NULL', [$code]);
    }
}
