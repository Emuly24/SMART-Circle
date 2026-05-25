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
?>
<!DOCTYPE html><html><head><title>Export Covered Topics</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card"><h2>Export Covered Topics</h2>
        <form action="admin_export_covered_topics.php" method="get">
            <div class="form-group"><label>Class:</label><select name="class"><option value="all">All</option><option value="Form 3">Form 3</option><option value="Form 4">Form 4</option></select></div>
            <button type="submit" class="btn">Download CSV</button>
        </form></div>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>