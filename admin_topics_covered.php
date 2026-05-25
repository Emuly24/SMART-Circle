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
$covered = $conn->query("SELECT * FROM topics_covered ORDER BY covered_date DESC");
?>
<!DOCTYPE html><html><head><title>Covered Topics</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Covered Topics</h1>
        <?php if($covered->num_rows == 0): ?>
            <div class="card"><p>No topics marked as covered yet. Use "Mark Covered" from topic requests.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Subject</th><th>Topic</th><th>Class</th><th>Covered Date</th></tr></thead>
                <tbody>
                <?php while($c=$covered->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['subject']) ?></td>
                        <td><?= htmlspecialchars($c['topic']) ?></td>
                        <td><?= $c['class_level'] ?></td>
                        <td><?= $c['covered_date'] ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>