<?php
require_once 'config.php';
session_start();
$admin_hash = getAdminHash();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'approve') {
        $conn->query("UPDATE testimonials SET status='approved', approved_at=NOW() WHERE id=$id");
    } elseif ($action === 'reject') {
        $conn->query("UPDATE testimonials SET status='rejected' WHERE id=$id");
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM testimonials WHERE id=$id");
    }
    header("Location: admin_testimonials.php");
    exit;
}
$testimonials = $conn->query("SELECT * FROM testimonials ORDER BY status='pending' DESC, created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Testimonials</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <h1>Student Testimonials</h1>
    <div class="content-grid">
        <?php while($t = $testimonials->fetch_assoc()): ?>
            <div class="card">
                <h3><?= htmlspecialchars($t['fullname']) ?> (<?= $t['class_level'] ?>)</h3>
                <p><strong>Rating:</strong> <?= str_repeat('⭐', $t['rating']) ?> (<?= $t['rating'] ?>/5)</p>
                <p><em>"<?= nl2br(htmlspecialchars($t['testimonial'])) ?>"</em></p>
                <p><strong>Status:</strong> <?= ucfirst($t['status']) ?> | Submitted: <?= $t['created_at'] ?></p>
                <div class="card-buttons">
                    <?php if ($t['status'] === 'pending'): ?>
                        <a href="?action=approve&id=<?= $t['id'] ?>" class="btn-success">Approve</a>
                        <a href="?action=reject&id=<?= $t['id'] ?>" class="btn-danger">Reject</a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?= $t['id'] ?>" class="btn-danger" onclick="return confirm('Delete this testimonial?')">Delete</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <div class="footer"><a href="admin_dashboard.php">← Back</a></div>
</div>
</body>
</html>