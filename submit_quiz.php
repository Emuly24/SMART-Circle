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
$quiz_id = (int)$_GET['quiz_id'];

$quiz_stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$quiz_stmt->bind_param("i", $quiz_id);
$quiz_stmt->execute();
$quiz = $quiz_stmt->get_result()->fetch_assoc();
if (!$quiz) die("Quiz not found.");

// ===== NO LOCKING – all quizzes are accessible =====

$note = $conn->query("SELECT title FROM notes WHERE id={$quiz['note_id']}")->fetch_assoc();

$attempt_stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
$attempt_stmt->bind_param("ii", $uid, $quiz_id);
$attempt_stmt->execute();
$attempt = $attempt_stmt->get_result()->fetch_assoc();

if (!$attempt) {
    $stmt = $conn->prepare("INSERT INTO quiz_attempts (user_id, quiz_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $uid, $quiz_id);
    $stmt->execute();
    $attempt_stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
    $attempt_stmt->bind_param("ii", $uid, $quiz_id);
    $attempt_stmt->execute();
    $attempt = $attempt_stmt->get_result()->fetch_assoc();
}

if ($attempt['status'] == 'submitted') {
    die("You have already submitted this quiz. <a href='quiz_results.php?quiz_id=$quiz_id'>View results</a>");
}

$questions_stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order");
$questions_stmt->bind_param("i", $quiz_id);
$questions_stmt->execute();
$questions = $questions_stmt->get_result();

$time_limit = $quiz['time_limit'] * 60;
$start = strtotime($attempt['started_at']);
$now = time();
$remaining = $start + $time_limit - $now;

if ($remaining <= 0) {
    echo "<script>alert('Time is up! Submitting...'); window.location='submit_quiz.php?quiz_id=$quiz_id';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Take Quiz</title><link rel="stylesheet" href="style.css"><script>let remaining=<?=$remaining?>; function timer(){if(remaining<=0){document.getElementById('timer').innerHTML="Submitting..."; window.location='submit_quiz.php?quiz_id=<?=$quiz_id?>';} let mins=Math.floor(remaining/60); let secs=remaining%60; document.getElementById('timer').innerHTML=`Time left: ${mins}m ${secs}s`; remaining--; setTimeout(timer,1000);} window.onload=timer;</script></head><body><div class="container"></div>
<form method="post" action="submit_quiz.php">
<input type="hidden" name="quiz_id" value="<?=$quiz_id?>">
<?php while($q=$questions->fetch_assoc()): ?>
<div class="card"><strong><?=htmlspecialchars($q['question_text'])?></strong> (<?=$q['points']?> pts)<br>
<?php if($q['question_type']=='multiple_choice'): $opts=json_decode($q['options'],true); foreach($opts as $opt):?>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>"> <?=htmlspecialchars($opt)?></label><br>
<?php endforeach; ?>
<?php elseif($q['question_type']=='true_false'): ?>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="True"> True</label><br>
<label><input type="radio" name="answer[<?=$q['id']?>]" value="False"> False</label>
<?php else: ?>
<textarea name="answer[<?=$q['id']?>]" rows="2"></textarea>
<?php endif; ?></div>
<?php endwhile; ?>
<button type="submit">Submit Quiz</button>
</form></div><?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>