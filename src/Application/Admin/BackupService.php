<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admin;

use SmartCircle\Data\Repositories\AuditLogRepository;
use SmartCircle\Data\Repositories\BackupRepository;

/**
 * A full copy of the database as SQL, for the admin to download and keep safe (free hosting can
 * lose data). It contains personal data, so only the admin can make one and every download is logged.
 */
final class BackupService
{
    public function __construct(
        private readonly BackupRepository $backup,
        private readonly AuditLogRepository $audit,
    ) {
    }

    /** @return array{filename: string, body: string, type: string} */
    public function make(int $adminId, string $ip): array
    {
        $out = "-- SMART Circle database backup\n-- Made " . date('Y-m-d H:i:s T') . "\n-- Contains personal data: keep it private.\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach ($this->backup->tables() as $table) {
            $out .= "DROP TABLE IF EXISTS `$table`;\n" . $this->backup->createStatement($table) . ";\n";
            $batch = [];
            foreach ($this->backup->rows($table) as $row) {
                $cols ??= '(`' . implode('`, `', array_keys($row)) . '`)';
                $batch[] = '(' . implode(', ', array_map(fn ($v): string => $this->backup->quote($v), $row)) . ')';
                if (count($batch) === 100) {
                    $out .= "INSERT INTO `$table` $cols VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $out .= "INSERT INTO `$table` $cols VALUES\n" . implode(",\n", $batch) . ";\n";
            }
            unset($cols);
            $out .= "\n";
        }
        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $this->audit->record($adminId, 'backup_downloaded', strlen($out) . ' bytes', $ip);
        $name = 'smartcircle-backup-' . date('Y-m-d-His');
        if (function_exists('gzencode')) {
            return ['filename' => $name . '.sql.gz', 'body' => (string) gzencode($out, 6), 'type' => 'application/gzip'];
        }

        return ['filename' => $name . '.sql', 'body' => $out, 'type' => 'application/sql'];
    }
}
