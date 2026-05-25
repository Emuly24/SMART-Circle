<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();

if (isset($_GET['update'])) {
    $id = (int)$_GET['id'];
    $status = $_POST['status'];
    $response = $_POST['admin_response'] ?? '';
    $conn->query("UPDATE student_reports SET status='$status', admin_response='$response' WHERE id=$id");
    header("Location: admin_reports.php");
    exit;
}
$reports = $conn->query("SELECT r.*, u.fullname, u.class_level FROM student_reports r JOIN users u ON r.user_id=u.id ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html><head><title>Student Reports</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Student Reports</h1>
        <?php if($reports->num_rows == 0): ?>
            <div class="card"><p>No student reports submitted.</p></div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Class</th><th>Type</th><th>Description</th><th>Incident Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php while($r = $reports->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['fullname']) ?></td>
                        <td><?= $r['class_level'] ?></td>
                        <td><?= $r['report_type'] ?></td>
                        <td><?= nl2br(htmlspecialchars($r['description'])) ?></td>
                        <td><?= $r['incident_date'] ?></td>
                        <td><?= $r['status'] ?></td>
                        <td>
                            <form method="post" action="?update=1&id=<?= $r['id'] ?>">
                                <select name="status">
                                    <option value="pending" <?= ($r['status']=='pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="reviewed" <?= ($r['status']=='reviewed') ? 'selected' : '' ?>>Reviewed</option>
                                    <option value="resolved" <?= ($r['status']=='resolved') ? 'selected' : '' ?>>Resolved</option>
                                </select>
                                <textarea name="admin_response" placeholder="Response"><?= htmlspecialchars($r['admin_response']) ?></textarea>
                                <button type="submit" class="btn">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>