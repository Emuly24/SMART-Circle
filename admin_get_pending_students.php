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
if (!$note_id) exit;
$students = $conn->query("SELECT DISTINCT u.id, u.fullname FROM exercise_attempts a JOIN users u ON a.user_id=u.id JOIN note_exercises e ON a.exercise_id=e.id WHERE e.note_id=$note_id AND a.status='paper_pending' AND a.promised_at < DATE_SUB(NOW(), INTERVAL 23 HOUR)");
if ($students->num_rows == 0) {
    echo "<p>No pending students for this note.</p>";
} else {
    echo "<div class='checkbox-group'>";
    while($s = $students->fetch_assoc()) {
        echo "<label><input type='checkbox' name='student_ids[]' value='{$s['id']}'> " . htmlspecialchars($s['fullname']) . "</label>";
    }
    echo "</div>";
}
?>