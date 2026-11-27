<?php

declare(strict_types=1);

namespace SmartCircle\Data\Repositories;

use SmartCircle\Data\Database;

/** Reads every table so the admin can download a full copy of the database. */
final class BackupRepository
{
    /** Tables that are only temporary and are left out of a backup. */
    private const SKIP = ['sessions', 'login_attempts'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<string> */
    public function tables(): array
    {
        $out = [];
        foreach ($this->db->pdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $t) {
            if (!in_array((string) $t, self::SKIP, true)) {
                $out[] = (string) $t;
            }
        }

        return $out;
    }

    public function createStatement(string $table): string
    {
        $this->assertTable($table);
        $row = $this->db->pdo()->query('SHOW CREATE TABLE `' . $table . '`')->fetch(\PDO::FETCH_NUM);

        return (string) ($row[1] ?? '');
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function rows(string $table): \Generator
    {
        $this->assertTable($table);
        $stmt = $this->db->pdo()->query('SELECT * FROM `' . $table . '`');
        while (($r = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            yield $r;
        }
    }

    public function quote(mixed $value): string
    {
        return $value === null ? 'NULL' : $this->db->pdo()->quote((string) $value);
    }

    /** Table names come from SHOW TABLES, but are still checked before being put into SQL. */
    private function assertTable(string $table): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $table) || !in_array($table, $this->tables(), true)) {
            throw new \InvalidArgumentException('Unknown table');
        }
    }
}
