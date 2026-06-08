<?php
// ===== TEST SESSION – NO DATABASE, NO REDIRECTS =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use the same folder as login.php
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}
session_save_path($session_path);

session_start();

if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 1;
} else {
    $_SESSION['test']++;
}

echo "<h1>Session Test</h1>";
echo "Session ID: " . session_id() . "<br>";
echo "Test counter: " . $_SESSION['test'] . "<br>";
echo "Session file: " . $session_path . '/sess_' . session_id() . "<br>";
echo "File exists? " . (file_exists($session_path . '/sess_' . session_id()) ? '✅ YES' : '❌ NO') . "<br>";
echo "File size: " . (file_exists($session_path . '/sess_' . session_id()) ? filesize($session_path . '/sess_' . session_id()) : '0') . " bytes<br>";
echo "<a href='session_test.php'>Refresh</a>";