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

$resources_stmt = $conn->prepare("SELECT * FROM student_resources WHERE user_id = ? ORDER BY created_at DESC");
$resources_stmt->bind_param("i", $uid);
$resources_stmt->execute();
$resources = $resources_stmt->get_result();
?>
<!DOCTYPE html>
<html><head><title>My Shared Resources</title><link rel="stylesheet" href="style.css"></head><body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <h1>My Shared Resources</h1>
    <div class="content-grid">
        <?php if ($resources->num_rows == 0): ?>
            <div class="card"><p>You haven't shared any resources yet.</p></div>
        <?php else: ?>
            <?php while($r = $resources->fetch_assoc()): 
                $type_label = '';
                if ($r['resource_type'] == 'book') $type_label = '📖 Book';
                elseif ($r['resource_type'] == 'past_paper') $type_label = '📝 Past Paper';
                elseif ($r['resource_type'] == 'notes') $type_label = '📑 Notes';
                else $type_label = '🔗 Other';
            ?>
                <div class="card">
                    <h3><?= htmlspecialchars($r['title']) ?> <small>(<?= $type_label ?>)</small></h3>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($r['subject']) ?></p>
                    <p><strong>Status:</strong> 
                        <?php if ($r['status'] == 'approved'): ?>
                            <span class="status-badge status-active">Approved</span>
                        <?php elseif ($r['status'] == 'rejected'): ?>
                            <span class="status-badge status-dismissed">Rejected</span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Pending</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($r['status'] == 'approved'): ?>
                        <?php if ($r['resource_type'] == 'book' && !$r['is_essential']): ?>
                            <p>✅ <strong>Your book has been added to the library!</strong> You can find it in the <a href="library.php">Library</a> section.</p>
                        <?php elseif ($r['resource_type'] == 'book' && $r['is_essential']): ?>
                            <p>📌 <strong>Your book has been marked as an essential internal resource.</strong> It will be used by the admin for teaching, but may not appear in the student library.</p>
                        <?php else: ?>
                            <p>📚 <strong>Your resource has been reviewed and will be used by the admin for creating teaching materials.</strong> Thank you for contributing!</p>
                        <?php endif; ?>
                        <?php if (!empty($r['admin_notes'])): ?>
                            <p><strong>Admin note:</strong> <?= nl2br(htmlspecialchars($r['admin_notes'])) ?></p>
                        <?php endif; ?>
                    <?php elseif ($r['status'] == 'rejected'): ?>
                        <p>❌ <strong>Your resource was not approved.</strong></p>
                        <?php if (!empty($r['admin_notes'])): ?>
                            <p><strong>Reason:</strong> <?= nl2br(htmlspecialchars($r['admin_notes'])) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>⏳ <strong>Pending review by admin.</strong> You will be notified once a decision is made.</p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>