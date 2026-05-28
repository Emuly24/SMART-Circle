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
$conn = getDB();
$quiz_id = (int)$_GET['quiz_id'];
$quiz = $conn->query("SELECT * FROM quizzes WHERE id=$quiz_id")->fetch_assoc();
if (!$quiz) die("Quiz not found.");
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id=$quiz_id ORDER BY sort_order");
?>
<!DOCTYPE html><html><head><title>Edit Questions</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container">
<?php while($q=$questions->fetch_assoc()): ?>
<div class="card"><strong>Q<?=$q['sort_order']?>:</strong> <?=nl2br(htmlspecialchars($q['question_text']))?> (<?=$q['points']?> pts)<br><a href="?delete_question=<?=$q['id']?>" onclick="return confirm('Delete?')">Delete</a></div>
<?php endwhile; ?>
<p><a href="admin_manage_quiz.php?note_id=<?=$quiz['note_id']?>">Back to Quiz Manager</a></p></div><div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>

<<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>