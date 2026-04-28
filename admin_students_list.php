<?php
// admin_students_list.php (full)
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
}
$conn = getDB();
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id = $user_id");
    header("Location: admin_students_list.php");
    exit;
}
$filter = $_GET['filter'] ?? 'all';
$where = "approved=1 AND status!='dismissed'";
if ($filter == 'suspended') $where = "status='suspended'";
elseif ($filter == 'pending') $where = "approved=0";
$students = $conn->query("SELECT id, fullname, class_level, status, suspension_end, approved FROM users WHERE $where ORDER BY class_level");
?>
<!DOCTYPE html><html><head><title>Student List</title><link rel="stylesheet" href="style.css"></head><body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>👥 Student List</h1>
    <div><a href="?filter=all">All Active</a> | <a href="?filter=suspended">Suspended</a> | <a href="?filter=pending">Pending Approval</a></div>
    <div class="grid">
    <?php while($s=$students->fetch_assoc()): ?>
        <div class="card">
            <h3><?= htmlspecialchars($s['fullname']) ?></h3>
           <p>
    Class: <?= $s['class_level'] ?><br>
    Status: 
    <?php
        if ($s['status'] === 'active') {
            echo '<span class="status-badge status-active">Active</span>';
        } elseif ($s['status'] === 'suspended') {
            echo '<span class="status-badge status-suspended">Suspended</span>';
            if ($s['suspension_end']) echo " until ".$s['suspension_end'];
        } elseif (!$s['approved']) {
            echo '<span class="status-badge status-pending">Pending Approval</span>';
        } else {
            echo htmlspecialchars($s['status']);
        }
    ?>
</p>
            <a href="admin_view_student.php?id=<?= $s['id'] ?>">View Details</a>
            <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Permanently delete this student and all their data? This cannot be undone.')" style="background:#e74c3c; color:white; margin-left:0.5rem;">Delete</a>
        </div>
    <?php endwhile; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php">← Back</a></div>
</div>
</body></html>