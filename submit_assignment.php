<?php
// ===== SESSION SETUP =====
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}
session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: login.php");
    exit;
}

require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$aid = (int)$_GET['assignment_id'];

// Check if already submitted
$check_stmt = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $aid, $uid);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows) {
    die("Already submitted.");
}

$as_stmt = $conn->prepare("SELECT title FROM assignments WHERE id = ?");
$as_stmt->bind_param("i", $aid);
$as_stmt->execute();
$as = $as_stmt->get_result()->fetch_assoc();
if (!$as) die("Invalid assignment.");

// ===== NO LOCKING – all assignments are accessible =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['submission_text']);
    $file = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $dest = $dir . "user_{$uid}_assign_{$aid}_" . time() . ".$ext";
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $dest)) $file = $dest;
    }
    if (empty($text) && !$file) die("Provide text or file.");
    
    $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, user_id, submission_text, file_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $aid, $uid, $text, $file);
    $stmt->execute();
    
    if (function_exists('log_activity')) {
        log_activity($uid, "submit_assignment", "Assignment ID: $aid");
    }
    echo "<script>alert('Submitted'); window.location='assignments.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Submit Assignment</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>📝 Submit Assignment</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Your Answer (text)</label><textarea name="submission_text" rows="6" class="form-control"></textarea></div>
                <div class="form-group"><label>OR Upload File</label><input type="file" name="submission_file" accept=".jpg,.png,.pdf,.doc,.txt" class="form-control"></div>
                <button type="submit" class="btn">Submit</button>
            </form>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>