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
    $conn->query("DELETE FROM assignments WHERE id=$id");
    header("Location: admin_assignments_list.php");
    exit;
}
$assignments = $conn->query("SELECT * FROM assignments ORDER BY due_date ASC");
?>
<!DOCTYPE html><html><head><title>Manage Assignments</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Manage Assignments</h1><a href="admin_create_assignment.php" class="btn">+ New Assignment</a></div>
        <?php if($assignments->num_rows == 0): ?>
            <div class="card"><p>No assignments yet. Click "New Assignment" to create one.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Subject</th><th>Class</th><th>Due</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($a=$assignments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['title']) ?></td>
                        <td><?= $a['subject'] ?></td>
                        <td><?= $a['class_level'] ?></td>
                        <td><?= $a['due_date'] ?></td>
                        <td class="card-buttons"><a href="admin_edit_assignment.php?id=<?= $a['id'] ?>">Edit</a> | <a href="?delete=<?= $a['id'] ?>" onclick="return confirm('Delete?')" class="btn-danger">Delete</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
      <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>