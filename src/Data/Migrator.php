<?php

declare(strict_types=1);

namespace SmartCircle\Data;

use RuntimeException;

/**
 * Applies database/migrations/NNNN_name.sql files in order, once each.
 * Runs from the web (installer / admin "Database updates" page): there is no shell on InfinityFree.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> versions not yet applied, oldest first */
    public function pending(): array
    {
        $applied = array_flip($this->applied());

        return array_values(array_filter(
            array_keys($this->files()),
            static fn (string $version): bool => !isset($applied[$version])
        ));
    }

    /** @return list<string> versions applied (empty if the tracking table does not exist yet) */
    public function applied(): array
    {
        $exists = $this->db->scalar(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
        );
        if ($exists === null) {
            return [];
        }

        return array_map('strval', array_column($this->db->all('SELECT version FROM schema_migrations ORDER BY version'), 'version'));
    }

    /** @return list<string> versions applied by this call */
    public function migrate(): array
    {
        $this->db->run(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    VARCHAR(100) NOT NULL PRIMARY KEY,
                applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $files = $this->files();
        $done = [];

        foreach ($this->pending() as $version) {
            $sql = file_get_contents($files[$version]);
            if ($sql === false) {
                throw new RuntimeException("Cannot read migration $version");
            }
            // MySQL commits DDL immediately, so each statement runs on its own.
            foreach (self::split($sql) as $statement) {
                $this->db->pdo()->exec($statement);
            }
            $this->db->run('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
            $done[] = $version;
        }

        return $done;
    }

    /**
     * Split a migration file into statements on semicolons at line ends.
     * Migration files must not contain semicolons at the end of a line inside strings.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*$/m', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }

    /** @return array<string, string> version => path, sorted */
    private function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }
}
