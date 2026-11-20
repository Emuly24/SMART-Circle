<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = getAdminHash();
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