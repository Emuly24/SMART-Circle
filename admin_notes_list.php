<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();

// Handle delete action
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM notes WHERE id=$id");
    header("Location: admin_notes_list.php");
    exit;
}

// Fetch all notes (they will be unique due to your UNIQUE KEY)
$notes = $conn->query("SELECT id, title, subject, class_level, created_at FROM notes ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Manage Notes</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="flex-between"><h1>Manage Notes</h1><a href="admin_note_editor.php" class="btn">+ New Note</a></div>
        <?php if($notes->num_rows == 0): ?>
            <div class="card"><p>No notes yet. Click "New Note" to create one.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Title</th><th>Subject</th><th>Class</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($n = $notes->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['title']) ?></td>
                        <td><?= $n['subject'] ?></td>
                        <td><?= $n['class_level'] ?></td>
                        <td><?= $n['created_at'] ?></td>
                        <td class="card-buttons">
                        <a href="admin_view_note.php?id=<?= $n['id'] ?>" class="btn">View</a>
                        <a href="admin_note_editor.php?id=<?= $n['id'] ?>" class="btn">Edit</a>
                        <a href="?delete=<?= $n['id'] ?>" onclick="return confirm('Delete this note?')" class="btn-danger">Delete</a>
                    </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>