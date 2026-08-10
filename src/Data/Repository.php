<?php

declare(strict_types=1);

namespace SmartCircle\Data;

use PDO;

/**
 * Base class for domain repositories — extend this in src/Data/*Repository.php.
 */
abstract class Repository
{
    protected PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }
}
