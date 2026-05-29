<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = $_SESSION['user_id'];
$conn = getDB();
$user = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
?>
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