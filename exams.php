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

if (!isset($_SESSION['class_level'])) {
    $user_stmt = $conn->prepare("SELECT class_level FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $uid);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_row = $user_result->fetch_assoc();
    $_SESSION['class_level'] = $user_row['class_level'] ?? 'Form 3';
}
$class = $_SESSION['class_level'];

$exams_stmt = $conn->prepare("SELECT e.*, 
    (SELECT status FROM exam_submissions WHERE exam_id = e.id AND user_id = ?) as status 
    FROM exams e 
    WHERE e.class_level = ? 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type = 'exam' AND gcl.content_id = e.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id = ?) 
                AND gcl.is_locked = 0)
    ORDER BY e.created_at DESC");
$exams_stmt->bind_param("isi", $uid, $class, $uid);
$exams_stmt->execute();
$exams = $exams_stmt->get_result();
?>
<!DOCTYPE html><html><head><title>Exams</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php while($e = $exams->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($e['title']) ?> (<?= htmlspecialchars($e['subject']) ?>)</h3>
                    <p><?= htmlspecialchars($e['description']) ?></p>
                    <p>Duration: <?= $e['duration_minutes'] ?> min</p>
                    <div class="card-buttons">
                        <?php if($e['status'] == 'in_progress'): ?>
                            <a href='take_exam.php?exam_id=<?= $e['id'] ?>'>Continue</a>
                        <?php elseif($e['status'] == 'submitted'): ?>
                            <a href='exam_results.php?exam_id=<?= $e['id'] ?>'>View Results</a>
                        <?php else: ?>
                            <a href='take_exam.php?exam_id=<?= $e['id'] ?>'>Start Exam</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
    </div>
</body></html>