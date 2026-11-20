<?php
// Bootstrap: loads .env, DB connection and the database-backed session (config.php is the only place that starts a session).
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}


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