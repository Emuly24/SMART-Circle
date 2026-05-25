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
$log = $conn->query("SELECT d.*, u.fullname, u.class_level FROM discipline_log d JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC");
?>
<!DOCTYPE html><html><head><title>Discipline Log</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Discipline Log</h1>
        <?php if($log->num_rows == 0): ?>
            <div class="card"><p>No discipline actions recorded.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Action</th><th>Reason</th><th>Suspension End</th></tr></thead>
                <tbody>
                <?php while($r=$log->fetch_assoc()): ?>
                    <tr>
                        <td><?= $r['created_at'] ?></td>
                        <td><?= htmlspecialchars($r['fullname']) ?></td>
                        <td><?= $r['class_level'] ?></td>
                        <td><?= strtoupper($r['action']) ?></td>
                        <td><?= htmlspecialchars($r['reason']) ?></td>
                        <td><?= $r['suspension_end'] ?? '-' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>