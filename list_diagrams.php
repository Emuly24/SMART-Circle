<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();

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