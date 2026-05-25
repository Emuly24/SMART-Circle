<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role']; 
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