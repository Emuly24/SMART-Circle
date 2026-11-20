<?php

declare(strict_types=1);

// config.php registers the database session handler and starts the session.
// It must be loaded so that session_destroy() deletes the row in the `sessions` table.
require_once __DIR__ . '/config.php';

// Record the logout before the session data is wiped (non-fatal if logging fails).
if (!empty($_SESSION['user_id'])) {
    try {
        log_activity((int) $_SESSION['user_id'], 'logout');
    } catch (Throwable $e) {
        error_log('Logout activity log failed: ' . $e->getMessage());
    }
}

// 1. Clear all session data in memory.
$_SESSION = [];

// 2. Expire the session cookie in the browser.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

// 3. Destroy the session: calls sess_destroy(), which DELETEs the row from `sessions`.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: index.php');
exit;
