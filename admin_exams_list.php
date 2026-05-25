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
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM exams WHERE id=$id");
    header("Location: admin_exams_list.php");
    exit;
}
$exams = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM exam_questions WHERE exam_id=e.id) as qcnt FROM exams e ORDER BY e.created_at DESC");
?>
<!DOCTYPE html><html><head><title>Manage Exams</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Manage Exams</h1><a href="admin_create_exam.php" class="btn">+ Create New Exam</a></div>
        <?php if($exams->num_rows == 0): ?>
            <div class="card"><p>No exams yet. Click "Create New Exam" to start.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Duration</th><th>Questions</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($e=$exams->fetch_assoc()): ?>
                    <tr>
                        <td><?= $e['id'] ?></td>
                        <td><?= htmlspecialchars($e['title']) ?></td>
                        <td><?= $e['subject'] ?></td>
                        <td><?= $e['class_level'] ?></td>
                        <td><?= $e['duration_minutes'] ?></td>
                        <td><?= $e['qcnt'] ?></td>
                        <td class="card-buttons"><a href="admin_add_questions.php?exam_id=<?= $e['id'] ?>">Edit Q's</a> | <a href="admin_mark_exams.php?exam_id=<?= $e['id'] ?>">Mark</a> | <a href="?delete=<?= $e['id'] ?>" onclick="return confirm('Delete?')" class="btn-danger">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>