<?php

declare(strict_types=1);

namespace SmartCircle\Data;

use PDO;
use PDOException;
use RuntimeException;
use SmartCircle\Support\Env;

/**
 * Central PDO connection — the only entry point for database access in new code.
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    private function __construct()
    {
        $host = Env::require('DB_HOST');
        $name = Env::require('DB_NAME');
        $user = Env::require('DB_USER');
        $pass = Env::get('DB_PASS', '') ?? '';

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $host,
            $name
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Database connection failed. Please try again later.',
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Verify the connection is still alive (handles MySQL "server has gone away"). */
    public function isConnected(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** Reset singleton — useful after long idle periods in CLI/cron scripts. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __clone() {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize Database singleton.');
    }
}
