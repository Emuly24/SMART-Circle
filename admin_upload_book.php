<?php
require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
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
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $dir = 'uploads/books/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
    if ($ext != 'pdf') $msg = "Only PDF allowed.";
    else {
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['pdf_file']['name']);
        $dest = $dir . $name;
        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
            $conn->query("INSERT INTO books (title,subject,class_level,file_path) VALUES ('$title','$subject','$class','$dest')");
            $msg = "Book uploaded.";
        } else $msg = "Upload failed.";
    }
}
?>
<!DOCTYPE html><html><head><title>Upload Book</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card"><h2>Upload Book (PDF)</h2>
        <?php if($msg) echo "<div class='success'>$msg</div>"; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
            <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
            <div class="form-group"><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select></div>
            <div class="form-group"><label>PDF file</label><input type="file" name="pdf_file" accept="application/pdf" required></div>
            <button type="submit" class="btn">Upload</button>
        </form></div>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>