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
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];

$book_id = (int)$_POST['book_id'];
$book_title = trim($_POST['book_title']);
$page_number = (int)$_POST['page_number'];
$selected_text = trim($_POST['selected_text']);
$question = trim($_POST['question']);

if (!$book_id || !$page_number || empty($selected_text) || empty($question)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO book_questions (user_id, book_id, book_title, page_number, selected_text, question, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iisiss", $uid, $book_id, $book_title, $page_number, $selected_text, $question);
$stmt->execute();

echo json_encode(['success' => true, 'id' => $conn->insert_id]);
$conn->close();
?>