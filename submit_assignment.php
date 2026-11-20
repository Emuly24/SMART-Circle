<?php
// Bootstrap: loads .env, DB connection and the database-backed session (config.php is the only place that starts a session).
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: login.php");
    exit;
}

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
        // Only document/image types; anything else (e.g. .php) could be executed by the web server.
        $allowed_types = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'txt'  => ['text/plain'],
        ];
        $max_bytes = 10 * 1024 * 1024; // InfinityFree upload limit is about 10 MB

        $ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['submission_file']['tmp_name']);

        if (!isset($allowed_types[$ext]) || !in_array($mime, $allowed_types[$ext], true)) {
            die("Invalid file type. Allowed: " . implode(', ', array_keys($allowed_types)) . ".");
        }
        if ($_FILES['submission_file']['size'] > $max_bytes) {
            die("File is too large (maximum 10 MB).");
        }

        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
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
                <div class="form-group"><label>OR Upload File</label><input type="file" name="submission_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt" class="form-control"></div>
                <button type="submit" class="btn">Submit</button>
            </form>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>