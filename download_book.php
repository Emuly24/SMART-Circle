<?php
require_once 'config.php';
require_once 'cookie_login.php';
require_once 'check_access.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();
$uid = $_SESSION['user_id'];
$book_id = (int)$_GET['id'];
$book = $conn->query("SELECT file_path FROM books WHERE id = $book_id")->fetch_assoc();
if (!$book || !file_exists($book['file_path'])) die("Book not found.");
$file = $book['file_path'];
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
?>