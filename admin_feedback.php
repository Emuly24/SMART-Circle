<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();

if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $conn->query("UPDATE student_messages SET status='read' WHERE id=$id");
    header("Location: admin_feedback.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM student_messages WHERE id=$id");
    header("Location: admin_feedback.php");
    exit;
}
$messages = $conn->query("SELECT m.*, u.fullname, u.class_level, u.phone 
    FROM student_messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Student Feedback</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Student Feedback</h1>
        <?php if ($messages->num_rows == 0): ?>
            <div class="card"><p>No messages yet.</p></div>
        <?php else: ?>
            <div class="grid">
            <?php while($m = $messages->fetch_assoc()): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($m['subject']) ?></h3>
                    <p><strong>From:</strong> <?= htmlspecialchars($m['fullname']) ?> (<?= $m['class_level'] ?>)<br>
                    <strong>Phone:</strong> <?= $m['phone'] ?><br>
                    <strong>Sent:</strong> <?= $m['created_at'] ?><br>
                    <strong>Status:</strong> <?= $m['status'] == 'unread' ? '<span class="status-badge status-pending">Unread</span>' : 'Read' ?></p>
                    <p><strong>Message:</strong><br><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                    <div class="card-buttons">
                        <?php if ($m['status'] == 'unread'): ?>
                            <a href="?mark_read=<?= $m['id'] ?>" class="btn">Mark as Read</a>
                        <?php endif; ?>
                        <a href="?delete=<?= $m['id'] ?>" class="btn-danger" onclick="return confirm('Delete this message?')">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php endif; ?>
       <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>