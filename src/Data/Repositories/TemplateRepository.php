<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** The admin's own wording for ready-made messages (document_templates). */
final class TemplateRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, array<string, mixed>> key => saved row */
    public function all(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT t.*, u.full_name AS updated_by_name FROM document_templates t LEFT JOIN users u ON u.id = t.updated_by') as $r) {
            $out[(string) $r['template_key']] = $r;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        return $this->db->one('SELECT * FROM document_templates WHERE template_key = ?', [$key]);
    }

    public function save(string $key, string $name, string $channel, ?string $subject, string $body, int $by): void
    {
        $this->db->run(
            'INSERT INTO document_templates (template_key, name, channel, subject, body, updated_by) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), channel = VALUES(channel), subject = VALUES(subject), body = VALUES(body), updated_by = VALUES(updated_by)',
            [$key, $name, $channel, $subject, $body, $by]
        );
    }

    public function delete(string $key): void
    {
        $this->db->run('DELETE FROM document_templates WHERE template_key = ?', [$key]);
    }
}
