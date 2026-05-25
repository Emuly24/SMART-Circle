<?php
require_once 'config.php';
require_once 'cookie_login.php';
require_once 'check_access.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];

$conn = getDB();
$uid = $user['id'];
$class = $user['class_level'];
$input = json_decode(file_get_contents('php://input'), true);
$book_id = (int)$input['book_id'];
$book_title = $input['book_title'];
$page = (int)$input['page'];
$selected_text = $input['selected_text'];
$question = $input['question'];

if (empty($book_id) || empty($question)) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}
$conn = getDB();
$stmt = $conn->prepare("INSERT INTO book_questions (user_id, book_id, book_title, page_number, selected_text, question) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisiss", $uid, $book_id, $book_title, $page, $selected_text, $question);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $conn->error]);
}
?>