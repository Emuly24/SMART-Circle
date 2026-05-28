<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($type) || empty($file)) die("Invalid request.");

$allowed_dirs = [
    'book'       => 'uploads/books/',
    'exam'       => 'uploads/exam_answers/',
    'assignment' => 'uploads/assignments/',
    'exercise'   => 'uploads/exercises/'   
];

if (!array_key_exists($type, $allowed_dirs)) die("Invalid type.");

$file_path = $allowed_dirs[$type] . $file;
$real_base = realpath($allowed_dirs[$type]);
$real_file = realpath($file_path);
if ($real_file === false || strpos($real_file, $real_base) !== 0) die("Invalid path.");
if (!file_exists($real_file)) die("File not found.");

$mime = mime_content_type($real_file);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($real_file) . '"');
header('Content-Length: ' . filesize($real_file));
readfile($real_file);
exit;
?>