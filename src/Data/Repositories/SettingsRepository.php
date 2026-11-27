<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Name/value programme settings. */
final class SettingsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $name): ?string
    {
        $v = $this->db->scalar('SELECT value FROM settings WHERE name = ?', [$name]);

        return $v === null ? null : (string) $v;
    }

    public function set(string $name, string $value): void
    {
        $this->db->run('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
    }
}
