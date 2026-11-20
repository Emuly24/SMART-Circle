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

// Students may only download their OWN exam answers, assignments and exercises.
// (File names are predictable, so the name alone is not a secret.) Books are shared.
$owner_queries = [
    'exam'       => 'SELECT 1 FROM exam_answers WHERE answer_file_path = ? AND user_id = ? LIMIT 1',
    'assignment' => 'SELECT 1 FROM assignment_submissions WHERE file_path = ? AND user_id = ? LIMIT 1',
    'exercise'   => 'SELECT 1 FROM exercise_attempts WHERE answer_file_path = ? AND user_id = ? LIMIT 1',
];
if (isset($owner_queries[$type])) {
    $owner_stmt = getPDO()->prepare($owner_queries[$type]);
    $owner_stmt->execute([$file_path, (int) $uid]);
    if (!$owner_stmt->fetchColumn()) {
        http_response_code(403);
        die("You do not have access to this file.");
    }
}

$mime = mime_content_type($real_file);
// Only render PDFs and images in the browser; force everything else to download
// so an uploaded HTML/SVG file cannot run script on this site.
$inline_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$disposition = in_array($mime, $inline_types, true) ? 'inline' : 'attachment';
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($real_file) . '"');
header('Content-Length: ' . filesize($real_file));
readfile($real_file);
exit;
?>