<?php
// includes/header.php - Common header for all pages
if (session_status() === PHP_SESSION_NONE) session_start();

$isAdminPage = (strpos($_SERVER['SCRIPT_NAME'], 'admin_') !== false);
$isStudent = !$isAdminPage && isset($_SESSION['user_id']);
$fullname = '';
$tagline = '';

if ($isStudent && isset($_SESSION['user_id'])) {
    $conn = getDB();
    $uid = $_SESSION['user_id'];
    $student = $conn->query("SELECT u.fullname, a.ambition, a.university 
        FROM users u 
        LEFT JOIN applications a ON u.id = a.user_id 
        WHERE u.id = $uid")->fetch_assoc();
    if ($student) {
        $fullname = $student['fullname'];
        if (!empty($student['ambition'])) {
            $tagline = 'aspiring ' . htmlspecialchars($student['ambition']);
            if (!empty($student['university'])) {
                $tagline .= ' aiming for ' . htmlspecialchars($student['university']);
            }
        }
    }
} elseif ($isAdminPage && isset($_SESSION['admin_logged'])) {
    $fullname = 'Admin';
    $tagline = 'SMART Tutor Manager';
}
?>

<nav class="top-nav">
    <div class="hamburger">
        <input type="checkbox" id="menu-toggle">
        <label for="menu-toggle" class="menu-icon">☰</label>
        <ul class="menu">
            <?php if ($isAdminPage): ?>
                <!-- Admin menu: only rarely used items -->
                <li><a href="admin_delete_covered_topics.php">🗑️ Delete Covered Topics</a></li>
                <li><a href="admin_export_covered_form.php">📎 Export Covered Topics</a></li>
                <li><a href="admin_attendance_report.php">📈 Attendance Report</a></li>
                <li><a href="admin_discipline_log.php">📜 Discipline Log</a></li>
                <li><a href="admin_class_overview.php">🏫 Class Overview</a></li>
                <li><a href="admin_backup.php">💾 Backup Database</a></li>
                <li><a href="admin_settings.php">⚙️ Settings</a></li>
                <li><a href="admin_notifications_center.php">🔔 Notifications Center</a></li>
                <li><a href="admin_feedback.php">💬 Student Feedback</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            <?php else: ?>
                <!-- Student menu (secondary links) -->
                <li><a href="profile.php">👤 My Profile</a></li>
                <li><a href="notifications.php">🔔 Notifications</a></li>
                <li><a href="student_message.php">📬 Contact Admin</a></li>
                <li><a href="student_report.php">⚠️ Submit a Report</a></li>
                <li><a href="request_topic.php">💡 Request Topic</a></li>
                <li><a href="covered_topics.php">📜 Covered Topics</a></li>
                <li><a href="logout.php">🚪 Logout</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="nav-title">
        <i class="fas fa-graduation-cap"></i> SMART Tutor
    </div>
    <div class="nav-user">
        <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
        <?php if ($tagline): ?>
            <span class="user-tagline"><?= htmlspecialchars($tagline) ?></span>
        <?php endif; ?>
    </div>
</nav>