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

$submissions_stmt = $conn->prepare("
    SELECT e.id, e.title, e.subject, s.total_score, s.status 
    FROM exam_submissions s 
    JOIN exams e ON s.exam_id = e.id 
    WHERE s.user_id = ? AND s.status IN ('submitted','marked') 
    ORDER BY s.end_time DESC
");
$submissions_stmt->bind_param("i", $uid);
$submissions_stmt->execute();
$submissions = $submissions_stmt->get_result();
?>
<!DOCTYPE html><html><head><title>My Results</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php while($r = $submissions->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($r['title']) ?> (<?= htmlspecialchars($r['subject']) ?>)</h3>
                    <p>Score: <?= ($r['total_score'] !== null) ? $r['total_score'] : 'Pending' ?></p>
                    <a href="exam_results.php?exam_id=<?= $r['id'] ?>">Details</a>
                </div>
            <?php endwhile; ?>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
    </div>
</body></html>