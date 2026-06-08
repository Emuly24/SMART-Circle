<?php
// ===== SESSION SETUP =====
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}
session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

// List diagrams from uploads/diagrams/ directory
$diagramsDir = __DIR__ . '/uploads/diagrams/';
$result = [];

if (is_dir($diagramsDir)) {
    $files = glob($diagramsDir . '*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
    foreach ($files as $file) {
        $result[] = [
            'url' => 'uploads/diagrams/' . basename($file),
            'name' => basename($file),
            'size' => filesize($file)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
?>