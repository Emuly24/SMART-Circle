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
    session_write_close();
    header("Location: login.php");
    exit;
}

require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];

$exam_id = (int)$_GET['exam_id'];
$exam_stmt = $conn->prepare("SELECT title FROM exams WHERE id = ?");
$exam_stmt->bind_param("i", $exam_id);
$exam_stmt->execute();
$exam = $exam_stmt->get_result()->fetch_assoc();

if (!$exam) die("Invalid exam");

$answers_stmt = $conn->prepare("
    SELECT q.question_text, a.answer_text, a.answer_file_path, a.marks_awarded, q.points, a.feedback
    FROM exam_questions q
    LEFT JOIN exam_answers a ON q.id = a.question_id AND a.exam_id = ? AND a.user_id = ?
    WHERE q.exam_id = ?
    ORDER BY q.sort_order
");
$answers_stmt->bind_param("iii", $exam_id, $uid, $exam_id);
$answers_stmt->execute();
$answers = $answers_stmt->get_result();
?>
<!DOCTYPE html><html><head><title><?= htmlspecialchars($exam['title']) ?> Results</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php while($a = $answers->fetch_assoc()): ?>
                <div class="card">
                    <strong>Q:</strong> <?= nl2br(htmlspecialchars($a['question_text'])) ?><br>
                    <strong>Your answer:</strong> <?= nl2br(htmlspecialchars($a['answer_text'])) ?>
                    <?php if($a['answer_file_path']) echo "<br><a href='download.php?type=exam&file=" . urlencode(basename($a['answer_file_path'])) . "' target='_blank'>View file</a>"; ?>
                    <br><strong>Marks:</strong> <?= ($a['marks_awarded'] !== null) ? $a['marks_awarded'] . '/' . $a['points'] : 'Pending' ?>
                    <br><?php if($a['feedback']) echo "<strong>Feedback:</strong> " . htmlspecialchars($a['feedback']); ?>
                </div>
            <?php endwhile; ?>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
        <?php include_once 'includes/testimonial_prompt.php'; ?>
    </div>
</body></html>