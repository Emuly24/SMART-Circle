<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = getAdminHash();
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