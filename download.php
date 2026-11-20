<?php
// Bootstrap: loads .env, DB connection and the database-backed session (config.php is the only place that starts a session).
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: login.php");
    exit;
}


$uid = $_SESSION['user_id'];
$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($type) || empty($file)) {
    die("Invalid request.");
}

$allowed_dirs = [
    'book'       => 'uploads/books/',
    'exam'       => 'uploads/exam_answers/',
    'assignment' => 'uploads/assignments/',
    'exercise'   => 'uploads/exercises/'
];

if (!array_key_exists($type, $allowed_dirs)) {
    die("Invalid file type.");
}

$file_path = $allowed_dirs[$type] . $file;
$real_base = realpath($allowed_dirs[$type]);
$real_file = realpath($file_path);

if ($real_file === false || strpos($real_file, $real_base) !== 0) {
    die("Invalid file path.");
}

if (!file_exists($real_file)) {
    die("File not found.");
}

$mime = mime_content_type($real_file);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($real_file) . '"');
header('Content-Length: ' . filesize($real_file));
readfile($real_file);
exit;
?>