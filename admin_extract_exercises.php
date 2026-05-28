<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$note_id = (int)$_GET['note_id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

// Fetch all exercises for this note
$exercises = $conn->query("SELECT * FROM note_exercises WHERE note_id=$note_id ORDER BY sort_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extract'])) {
    // Fetch group locks from the note
    $group_locks = $conn->query("SELECT * FROM group_content_locks WHERE content_type='note' AND content_id=$note_id");
    
    while ($ex = $exercises->fetch_assoc()) {
        $title = "Exercise " . $ex['sort_order'] . " from " . $note['title'];
        $desc = $ex['question'];
        
        // Insert as an assignment
        $conn->query("INSERT INTO assignments (title, description, subject, class_level, due_date, note_id, exercise_sort_order) 
                      VALUES ('$title', '$desc', '{$note['subject']}', '{$note['class_level']}', NULL, $note_id, {$ex['sort_order']})");
        $assignment_id = $conn->insert_id;
        
        // Copy lock settings from the note to the assignment
        $g_lock_res = $conn->query("SELECT * FROM group_content_locks WHERE content_type='note' AND content_id=$note_id");
        while ($lock = $g_lock_res->fetch_assoc()) {
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$lock['group_id']}, 'assignment', $assignment_id, {$lock['is_locked']})");
        }
    }
    
    echo "<script>alert('Exercises extracted to assignments!'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html><head><title>Extract Exercises</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding:2rem;">
            <h2>📤 Extract Exercises from "<?=htmlspecialchars($note['title'])?>"</h2>
            <p>The following exercises will be extracted as separate assignments:</p>
            <ul>
                <?php while($ex = $exercises->fetch_assoc()): ?>
                    <li>Exercise <?=$ex['sort_order']?> – <?=htmlspecialchars(substr($ex['question'], 0, 100))?>...</li>
                <?php endwhile; ?>
            </ul>
            <p><small>Each exercise will become a separate assignment. You can set the due date for each one in the <strong>Assignments List</strong> after extraction.</small></p>
            <form method="post">
                <button type="submit" name="extract" class="btn" style="background:var(--accent);">✅ Extract to Assignments</button>
                <a href="admin_note_editor.php?id=<?=$note_id?>" class="btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
</body></html>