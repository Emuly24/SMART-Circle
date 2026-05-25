<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();
$aid = (int)($_GET['assignment_id'] ?? 0);
$uid = (int)($_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $sub_id = (int)$_POST['submission_id'];
    $marks = (int)$_POST['marks'];
    $fb = $_POST['feedback'];
    $conn->query("UPDATE assignment_submissions SET marks=$marks, feedback='$fb', marked_by_admin=1 WHERE id=$sub_id");
    header("Location: admin_mark_assignments.php?assignment_id=$aid&user_id=$uid");
    exit;
}

if ($aid && $uid) {
    $sub = $conn->query("SELECT s.*, u.fullname, a.title FROM assignment_submissions s JOIN users u ON s.user_id=u.id JOIN assignments a ON s.assignment_id=a.id WHERE s.assignment_id=$aid AND s.user_id=$uid")->fetch_assoc();
    if (!$sub) die("Submission not found.");
    ?><html><head><title>Mark Assignment</title><link rel="stylesheet" href="style.css"></head><body>
        <?php include_once 'includes/header.php'; ?>
        <div class="container">
            <div class="card" style="padding:2rem;">
                <p><strong>Submission:</strong><br><?=nl2br(htmlspecialchars($sub['submission_text']))?><?php if($sub['file_path']) echo "<br><a href='admin_download.php?type=assignment&file=" . urlencode(basename($sub['file_path'])) . "' target='_blank'>View file</a>";?></p>
                <form method="post">
                    <input type="hidden" name="submission_id" value="<?=$sub['id']?>">
                    <div class="form-group"><label>Marks (out of ?)</label><input type="number" name="marks" value="<?=$sub['marks']?>"></div>
                    <div class="form-group"><label>Feedback</label><textarea name="feedback" rows="3"><?=htmlspecialchars($sub['feedback'])?></textarea></div>
                    <button type="submit" name="save_marks" class="btn">Save Marks</button>
                </form>
            </div>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
    </body></html><?php exit;
}

if ($aid) {
    $subs = $conn->query("SELECT s.user_id, u.fullname, s.marks FROM assignment_submissions s JOIN users u ON s.user_id=u.id WHERE s.assignment_id=$aid");
    $title = $conn->query("SELECT title FROM assignments WHERE id=$aid")->fetch_assoc()['title'];
    ?><html><head><title>Submissions</title><link rel="stylesheet" href="style.css"></head><body>
        <?php include_once 'includes/header.php'; ?>
        <div class="container">
            <h2><?= htmlspecialchars($title) ?> – Submissions</h2>
            <div class="content-grid">
                <?php if($subs->num_rows == 0): ?>
                    <div class="card"><p>No submissions for this assignment.</p></div>
                <?php else: ?>
                    <?php while($s=$subs->fetch_assoc()):?>
                        <div class="card">
                            <a href="admin_mark_assignments.php?assignment_id=<?=$aid?>&user_id=<?=$s['user_id']?>"><?=htmlspecialchars($s['fullname'])?></a>
                            <br>Marks: <?=$s['marks']??'Not marked'?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
    </body></html><?php exit;
}

$assignments = $conn->query("SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id=a.id) as subcnt FROM assignments a ORDER BY a.due_date DESC");
?>
<!DOCTYPE html><html><head><title>Mark Assignments</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Mark Assignments</h1>
        <div class="content-grid">
            <?php if($assignments->num_rows == 0): ?>
                <div class="card"><p>No assignments have been created yet.</p></div>
            <?php else: ?>
                <?php while($a=$assignments->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($a['title']) ?></h3>
                        <p>Due: <?= $a['due_date'] ?> | Submissions: <?= $a['subcnt'] ?></p>
                        <?php if($a['subcnt'] > 0): ?>
                            <a href="admin_mark_assignments.php?assignment_id=<?=$a['id']?>" class="btn">Mark Submissions</a>
                        <?php else: ?>
                            <p class="text-muted">No submissions yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>