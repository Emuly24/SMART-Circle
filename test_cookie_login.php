<?php
require_once 'config.php';
require_once 'cookie_login.php';

$login = checkLogin();
if ($login) {
    echo "✅ Login successful! user_id: {$login['user_id']}, role: {$login['role']}";
} else {
    echo "❌ No login token found. Cookie may not be set or expired.";
    echo "<br>COOKIE['auth_token']: " . ($_COOKIE['auth_token'] ?? 'not set');
}
?>