<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}
$conn = getDB();

// Answer a question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_question'])) {
    $qid = (int)$_POST['question_id'];
    $answer = trim($_POST['answer']);
    $admin_id = $_SESSION['admin_logged']; // dummy, not stored
    $conn->query("UPDATE subject_questions SET answer='$answer', status='answered', answered_by=1, answered_at=NOW() WHERE id=$qid");
    // Optionally send a notification to the student
    $q = $conn->query("SELECT user_id FROM subject_questions WHERE id=$qid")->fetch_assoc();
    $conn->query("INSERT INTO admin_messages (user_id, message) VALUES ({$q['user_id']}, 'Your question has been answered. Please check the subject page for the answer.')");
    header("Location: admin_subject_questions.php");
    exit;
}

$questions = $conn->query("SELECT sq.*, u.fullname, u.class_level FROM subject_questions sq JOIN users u ON sq.user_id=u.id ORDER BY sq.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Subject Questions</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Student Questions (by Subject)</h1>
    <div class="content-grid">
        <?php while($q = $questions->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($q['subject']) ?></h3>
                <p><strong>From:</strong> <?= htmlspecialchars($q['fullname']) ?> (<?= $q['class_level'] ?>)<br>
                <strong>Question:</strong> <?= nl2br(htmlspecialchars($q['question'])) ?></p>
                <?php if ($q['status'] == 'answered' && $q['answer']): ?>
                    <p><strong>Answer:</strong> <?= nl2br(htmlspecialchars($q['answer'])) ?></p>
                    <p><small>Answered on: <?= $q['answered_at'] ?></small></p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                        <textarea name="answer" rows="3" placeholder="Write your answer here..." required></textarea>
                        <button type="submit" name="answer_question" class="btn-success">Submit Answer</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php if ($questions->num_rows == 0): ?>
            <div class="card"><p>No student questions yet.</p></div>
        <?php endif; ?>
    <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>