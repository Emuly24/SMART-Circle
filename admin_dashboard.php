<?php
// Temporary fix: Define getAdminHash() directly here
function getAdminHash() {
    static $hash = null;
    if ($hash !== null) return $hash;
    $conn = new mysqli('sql302.infinityfree.com', 'if0_41797522', 'Emuly241295', 'if0_41797522_smarttutor');
    $result = $conn->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'admin_hash'");
    if ($result && $row = $result->fetch_assoc()) {
        $hash = $row['setting_value'];
    } else {
        $hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
    }
    return $hash;
}

require_once 'config.php';
$conn = getDB();
$total_students = $conn->query("SELECT COUNT(*) FROM users WHERE approved=1 AND status!='dismissed'")->fetch_row()[0];
$pending_apps = $conn->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetch_row()[0];
$pending_exams = $conn->query("SELECT COUNT(*) FROM exam_submissions WHERE status='submitted'")->fetch_row()[0];
$pending_assign = $conn->query("SELECT COUNT(DISTINCT s.id) FROM assignment_submissions s LEFT JOIN assignments a ON s.assignment_id=a.id WHERE s.marks IS NULL")->fetch_row()[0];
$suspensions = $conn->query("SELECT COUNT(*) FROM users WHERE status='suspended' AND suspension_end>=CURDATE()")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-page">
<div class="container">
    <?php include_once 'includes/header.php'; ?>

    <!-- Stats cards -->
    <div class="stats-container">
        <div class="stat-card-item">
            <i class="fas fa-users"></i>
            <div class="stat-number"><?= $total_students ?></div>
            <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-card-item">
            <i class="fas fa-clock"></i>
            <div class="stat-number"><?= $pending_apps ?></div>
            <div class="stat-label">Pending Applications</div>
        </div>
        <div class="stat-card-item">
            <i class="fas fa-file-alt"></i>
            <div class="stat-number"><?= $pending_exams ?></div>
            <div class="stat-label">Exams Pending Marking</div>
        </div>
        <div class="stat-card-item">
            <i class="fas fa-tasks"></i>
            <div class="stat-number"><?= $pending_assign ?></div>
            <div class="stat-label">Assignments Pending</div>
        </div>
        <div class="stat-card-item">
            <i class="fas fa-ban"></i>
            <div class="stat-number"><?= $suspensions ?></div>
            <div class="stat-label">Suspended Students</div>
        </div>
    </div>

    <!-- Admin action cards -->
    <div class="content-grid">
        <!-- Student Management Card -->
        <div class="card">
            <i class="fas fa-user-check"></i>
            <h3>Student Management</h3>
            <div class="card-buttons">
                <a href="admin_approve.php">Approve Applications</a>
                <a href="admin_students_list.php">Student List</a>
                <a href="admin_reassign_group.php">🔄 Reassign Student Group</a>
                <a href="admin_discipline.php">Discipline</a>
                <a href="admin_attendance.php">Mark Attendance</a>
                <a href="admin_reports.php">Student Reports</a>
                <a href="admin_testimonials.php">⭐ Manage Testimonials</a>
                <a href="admin_subject_questions.php">❓ Subject Questions</a>
                <a href="admin_activity_log.php">📜 Student Activity Log</a>
                <a href="admin_purge_records.php">🗑️ Purge Dismissed/Rejected Records</a>
            </div>
        </div>

        <!-- Content Card -->
<div class="card">
    <i class="fas fa-book"></i>
    <h3>Content</h3>
    <div class="card-buttons">
        <a href="admin_note_editor.php">Write Note</a>
        <a href="admin_notes_list.php">Manage Notes</a>
        <a href="admin_upload_book.php">Upload Book</a>
        <a href="admin_mark_exercises.php">Mark Exercises</a>
        <a href="admin_book_questions.php">📚 Book Questions</a>
        <a href="admin_bulk_import.php">📤 Bulk Import Notes/Books (CSV)</a>
        <a href="admin_resources.php">📂 Student Resource Submissions</a>
        <a href="admin_select_note_for_extraction.php">📤 Extract Exercises to Assignments</a>
    </div>
</div>
        <!-- Exams Card -->
        <div class="card">
            <i class="fas fa-pen-alt"></i>
            <h3>Exams</h3>
            <div class="card-buttons">
                <a href="admin_create_exam.php">Create Exam</a>
                <a href="admin_exams_list.php">Manage Exams</a>
                <a href="admin_mark_exams.php">Mark Submissions</a>
            </div>
        </div>

        <!-- Assignments Card -->
        <div class="card">
            <i class="fas fa-tasks"></i>
            <h3>Assignments</h3>
            <div class="card-buttons">
                <a href="admin_create_assignment.php">Create Assignment</a>
                <a href="admin_assignments_list.php">Manage Assignments</a>
                <a href="admin_mark_assignments.php">Mark Submissions</a>
            </div>
        </div>

        <!-- Topic Requests Card -->
        <div class="card">
            <i class="fas fa-lightbulb"></i>
            <h3>Topic Requests</h3>
            <div class="card-buttons">
                <a href="admin_topic_requests.php">View Requested Topics</a>
                <a href="admin_topics_covered.php">Covered Topics</a>
                <a href="admin_delete_covered_topics.php">🗑️ Delete Covered Topics</a>
                <a href="admin_export_covered_form.php">📎 Export Covered Topics</a>
            </div>
        </div>

        <!-- Class Management Card -->
        <div class="card">
            <i class="fas fa-chart-line"></i>
            <h3>Class Management</h3>
            <div class="card-buttons">
                <a href="admin_attendance_report.php">Attendance Report</a>
                <a href="admin_discipline_log.php">Discipline Log</a>
                <a href="admin_class_overview.php">Class Overview</a>
                <a href="admin_group_insights.php">📊 Group Insights Dashboard</a>
                <a href="admin_set_meeting.php">⏰ Set Group Meeting Time</a>
                <a href="admin_group_locks.php">🔒 Group Content Locks</a>
            </div>
        </div>

        <!-- Run Checks Card -->
        <div class="card">
    <i class="fas fa-play-circle"></i>
    <h3>Automated Checks</h3>
    <div class="card-buttons">
        <a href="admin_run_pending_exercises.php">⏰ Run Manual Checks (Exercises + Assignments)</a>
    </div>
</div>
       <!-- System Card -->
<div class="card">
    <i class="fas fa-cogs"></i>
    <h3>System</h3>
    <div class="card-buttons">
        <a href="admin_backup.php">Backup Database</a>
        <a href="admin_settings.php">Change Password</a>
        <a href="admin_notifications_center.php">Notifications Center</a>
        <a href="admin_feedback.php">Student Feedback</a>
        <a href="admin_backup_extract_all.php">📦 Full Extraction (Files + Database)</a>
    </div>
</div>

    <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>