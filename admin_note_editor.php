<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], ADMIN_HASH)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDB();
    $title = $_POST['title'];
    $subject = $_POST['subject'];
    $class = $_POST['class_level'];
    $content = $_POST['content'];
    $conn->query("INSERT INTO notes (title,subject,class_level,content) VALUES ('$title','$subject','$class','$content')");
    echo "<script>alert('Note saved');</script>";
}
?>
<!DOCTYPE html>
<html><head><title>Write Note</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<style>
    /* Make the editor fill the available width */
    .ck-editor__editable {
        min-height: 500px;
        width: 100% !important;
    }
    .ck-editor {
        width: 100% !important;
    }
</style>
</head>
<body>
    <?php include_once 'includes/header.php'; ?>
<div class="container"><div class="header"><h1>✍️ Write Note</h1><a href="admin_dashboard.php">Dashboard</a><a href="logout.php" class="logout">Logout</a></div>
<div style="padding: 2rem;">
    <form method="post">
        <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
        <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
        <div class="form-group"><label>Class</label><select name="class_level"><option>Form 3</option><option>Form 4</option></select></div>
        <div class="form-group"><label>Content</label><textarea name="content" id="editor"></textarea></div>
        <button type="submit">Save Note</button>
    </form>
</div>
<div class="footer"><a href="admin_notes_list.php">← Back to Notes List</a></div>
</div>
<script>
    ClassicEditor.create(document.querySelector('#editor'), {}).catch(console.error);
</script>
</body></html>