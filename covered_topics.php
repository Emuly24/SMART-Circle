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
$class = $_SESSION['class_level'] ?? '';

$covered_stmt = $conn->prepare("SELECT * FROM topics_covered WHERE class_level = ? ORDER BY covered_date DESC");
$covered_stmt->bind_param("s", $class);
$covered_stmt->execute();
$covered = $covered_stmt->get_result();
?>
<!DOCTYPE html><html><head><title>Covered Topics</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php if($covered->num_rows == 0): ?>
                <p>None yet.</p>
            <?php else: ?>
                <?php while($c = $covered->fetch_assoc()): ?>
                    <div class="card">
                        <strong><?= htmlspecialchars($c['subject']) ?>:</strong> <?= htmlspecialchars($c['topic']) ?><br>
                        <small>Covered: <?= htmlspecialchars($c['covered_date']) ?></small>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
    </div>
</body></html>