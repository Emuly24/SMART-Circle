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

$exam_stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$exam_stmt->bind_param("i", $exam_id);
$exam_stmt->execute();
$exam = $exam_stmt->get_result()->fetch_assoc();
if (!$exam) die("Exam not found.");

// ===== NO LOCKING – all exams are accessible =====

$sub_stmt = $conn->prepare("SELECT * FROM exam_submissions WHERE exam_id = ? AND user_id = ?");
$sub_stmt->bind_param("ii", $exam_id, $uid);
$sub_stmt->execute();
$sub = $sub_stmt->get_result()->fetch_assoc();

if ($sub && $sub['status'] == 'submitted') {
    die("Already submitted. <a href='exam_results.php?exam_id=$exam_id'>View results</a>");
}

if (!$sub) {
    $stmt = $conn->prepare("INSERT INTO exam_submissions (exam_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $exam_id, $uid);
    $stmt->execute();
    $sub = $conn->query("SELECT start_time FROM exam_submissions WHERE exam_id=$exam_id AND user_id=$uid")->fetch_assoc();
}

$start = new DateTime($sub['start_time']);
$end = (clone $start)->modify("+{$exam['duration_minutes']} minutes");

if (new DateTime() > $end) {
    $stmt = $conn->prepare("UPDATE exam_submissions SET status='submitted', end_time=NOW() WHERE exam_id=? AND user_id=?");
    $stmt->bind_param("ii", $exam_id, $uid);
    $stmt->execute();
    log_activity($uid, "submit_exam", "Exam ID: $exam_id");
    die("Time's up. Submitted. <a href='exam_results.php?exam_id=$exam_id'>View results</a>");
}

$remaining = $end->getTimestamp() - time();
$questions_stmt = $conn->prepare("SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY sort_order");
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions = $questions_stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    foreach ($_POST['answers'] as $qid => $text) {
        $text = trim($text);
        $file_path = null;
        if (isset($_FILES['answer_files']) && isset($_FILES['answer_files']['name'][$qid]) && $_FILES['answer_files']['error'][$qid] == UPLOAD_ERR_OK) {
            $dir = 'uploads/exam_answers/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['answer_files']['name'][$qid], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg','jpeg','png','pdf'])) {
                $dest = $dir . "user_{$uid}_exam_{$exam_id}_q{$qid}_" . time() . ".$ext";
                if (move_uploaded_file($_FILES['answer_files']['tmp_name'][$qid], $dest)) $file_path = $dest;
            }
        }
        $check_stmt = $conn->prepare("SELECT id FROM exam_answers WHERE exam_id=? AND question_id=? AND user_id=?");
        $check_stmt->bind_param("iii", $exam_id, $qid, $uid);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows) {
            $stmt = $conn->prepare("UPDATE exam_answers SET answer_text=?, answer_file_path=? WHERE exam_id=? AND question_id=? AND user_id=?");
            $stmt->bind_param("ssiii", $text, $file_path, $exam_id, $qid, $uid);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO exam_answers (exam_id, question_id, user_id, answer_text, answer_file_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $exam_id, $qid, $uid, $text, $file_path);
            $stmt->execute();
        }
    }
    $stmt = $conn->prepare("UPDATE exam_submissions SET status='submitted', end_time=NOW() WHERE exam_id=? AND user_id=?");
    $stmt->bind_param("ii", $exam_id, $uid);
    $stmt->execute();
    log_activity($uid, "submit_exam", "Exam ID: $exam_id");
    echo "<script>alert('Exam submitted'); window.location='exams.php';</script>";
    exit;
}

$saved = [];
$res_stmt = $conn->prepare("SELECT question_id, answer_text FROM exam_answers WHERE exam_id=? AND user_id=?");
$res_stmt->bind_param("ii", $exam_id, $uid);
$res_stmt->execute();
$res = $res_stmt->get_result();
while ($r = $res->fetch_assoc()) $saved[$r['question_id']] = $r['answer_text'];
?>
<!DOCTYPE html><html><head><title><?= htmlspecialchars($exam['title']) ?></title><link rel="stylesheet" href="style.css">
<script>let remaining=<?=$remaining?>; function timer(){if(remaining<=0){document.getElementById('timer').innerHTML="Submitting..."; document.getElementById('examForm').submit();} let mins=Math.floor(remaining/60); let secs=remaining%60; document.getElementById('timer').innerHTML=`Time left: ${mins}m ${secs}s`; remaining--; setTimeout(timer,1000);} window.onload=timer;</script>
</head><body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div id="timer" style="text-align:center;font-size:1.5rem;font-weight:bold;color:var(--accent);margin:1rem 0;"></div>
    <form id="examForm" method="post" enctype="multipart/form-data">
        <?php $qno=1; while($q=$questions->fetch_assoc()): ?>
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
            <b><?=$qno?>. <?=nl2br(htmlspecialchars($q['question_text']))?></b> (<?=$q['points']?> pts)<br>
            <?php if($q['question_type']!='multiple_choice'):?>
                <textarea name="answers[<?=$q['id']?>]" rows="4" class="form-control" style="width:100%;"><?=htmlspecialchars($saved[$q['id']]??'')?></textarea>
                <br>OR upload file: <input type="file" name="answer_files[<?=$q['id']?>]" accept=".jpg,.png,.pdf">
            <?php else: $opts=json_decode($q['options'],true); foreach($opts as $opt):?>
                <label><input type="radio" name="answers[<?=$q['id']?>]" value="<?=htmlspecialchars($opt)?>" <?=(($saved[$q['id']]??'')==$opt)?'checked':''?>> <?=$opt?></label><br>
            <?php endforeach; endif;?>
        </div>
        <?php $qno++; endwhile;?>
        <button type="submit" name="submit_exam" class="btn">Submit Exam</button>
    </form>
</div>
<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>