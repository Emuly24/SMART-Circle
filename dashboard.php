<?php
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}
session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CHECK IF USER IS LOGGED IN =====
if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

require_once 'config.php';
$conn = getDB();

// ===== FETCH FULL USER DATA (before check_access) =====
$stmt = $conn->prepare("SELECT id, fullname, class_level, status, approved, consent_signed, school, phone, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    session_write_close();
    header('Location: login.php');
    exit;
}

// Store class_level in session for other pages
$_SESSION['class_level'] = $user['class_level'];

// ===== CHECK ACCESS (now with full $user) =====
require_once 'check_access.php';

// ===== FETCH GROUP INFO =====
$group = null;
$fellow_members = [];
$group_stmt = $conn->prepare("SELECT g.id as group_id, g.group_number, g.class_level FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ?");
$group_stmt->bind_param("i", $uid);
$group_stmt->execute();
$group_result = $group_stmt->get_result();
if ($group_result->num_rows > 0) {
    $group = $group_result->fetch_assoc();
    $fellow_stmt = $conn->prepare("SELECT u.fullname, u.phone FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = ? AND u.id != ?");
    $fellow_stmt->bind_param("ii", $group['group_id'], $uid);
    $fellow_stmt->execute();
    $fellow_result = $fellow_stmt->get_result();
    while ($f = $fellow_result->fetch_assoc()) {
        $fellow_members[] = $f;
    }
}

// ===== STATISTICS (Combined query for efficiency) =====
$class = $user['class_level'];

$stats_stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM note_exercises e JOIN notes n ON e.note_id=n.id WHERE n.class_level=?) AS total_exercises,
        (SELECT COUNT(DISTINCT a.exercise_id) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=? AND n.class_level=? AND a.status='marked') AS done_exercises,
        (SELECT COUNT(*) FROM quizzes q JOIN notes n ON q.note_id=n.id WHERE n.class_level=?) AS total_quizzes,
        (SELECT COUNT(*) FROM quiz_attempts a JOIN quizzes q ON a.quiz_id=q.id JOIN notes n ON q.note_id=n.id WHERE a.user_id=? AND n.class_level=? AND (a.status='submitted' OR a.status='marked')) AS done_quizzes
");
$stats_stmt->bind_param("sissss", $class, $uid, $class, $class, $uid, $class);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$total_exercises = $stats['total_exercises'] ?? 0;
$done_exercises = $stats['done_exercises'] ?? 0;
$total_quizzes = $stats['total_quizzes'] ?? 0;
$done_quizzes = $stats['done_quizzes'] ?? 0;

// Attendance (30 days)
$att_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late FROM attendance WHERE user_id=? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$att_stmt->bind_param("i", $uid);
$att_stmt->execute();
$att_stats = $att_stmt->get_result()->fetch_assoc();
$att_total = $att_stats['total'] ?? 0;
$att_present = $att_stats['present'] ?? 0;
$att_late = $att_stats['late'] ?? 0;
$attendance_rate = $att_total ? round((($att_present + $att_late) / $att_total) * 100) : 0;

// Messages
$msg_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread FROM admin_messages WHERE user_id = ?");
$msg_stmt->bind_param("i", $uid);
$msg_stmt->execute();
$msg_row = $msg_stmt->get_result()->fetch_assoc();
$total_msgs = $msg_row['total'] ?? 0;
$unread_msgs = $msg_row['unread'] ?? 0;

// Paper pending exercises
$paper_stmt = $conn->prepare("SELECT COUNT(*) FROM exercise_attempts a JOIN note_exercises e ON a.exercise_id=e.id JOIN notes n ON e.note_id=n.id WHERE a.user_id=? AND a.status='paper_pending'");
$paper_stmt->bind_param("i", $uid);
$paper_stmt->execute();
$paper_count = $paper_stmt->get_result()->fetch_row()[0] ?? 0;

// Subjects
$subjects = [];
$subj_stmt = $conn->prepare("SELECT DISTINCT n.subject FROM notes n WHERE n.class_level=? AND EXISTS (SELECT 1 FROM group_content_locks gcl WHERE gcl.content_type='note' AND gcl.content_id=n.id AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=?) AND gcl.is_locked = 0) ORDER BY n.subject");
$subj_stmt->bind_param("si", $class, $uid);
$subj_stmt->execute();
$subj_result = $subj_stmt->get_result();
while ($s = $subj_result->fetch_assoc()) {
    $subjects[] = $s['subject'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .stats-grid { display: flex; gap: 1rem; flex-wrap: nowrap; margin: 1.5rem 0; justify-content: center; }
        .stats-grid .stat-card { background: var(--card-bg); border-radius: 1rem; padding: 1.2rem 1.5rem; box-shadow: var(--card-shadow); border: 1px solid rgba(0,0,0,0.05); flex: 1; min-width: 120px; text-align: center; transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1); position: relative; }
        .stats-grid .stat-card:hover { transform: translateY(-4px); box-shadow: var(--hover-shadow); border-color: var(--accent); }
        .stats-grid .stat-card i { font-size: 1.8rem; color: var(--accent); display: block; margin-bottom: 0.5rem; }
        .stats-grid .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; color: var(--text-color); line-height: 1.2; }
        .stats-grid .stat-card .stat-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stats-grid .stat-card .stat-sub { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        .notif-badge { position: absolute; top: 8px; right: 8px; background: var(--error); color: white; border-radius: 50%; min-width: 20px; height: 20px; padding: 0 4px; font-size: 0.7rem; font-weight: 700; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.4); }
        .notif-badge.zero { background: var(--success); box-shadow: 0 2px 8px rgba(22, 163, 74, 0.4); }
        .stats-grid .stat-card .notif-icon-wrap { position: relative; display: inline-block; }
        .subjects-horizontal { margin: 1rem 0; overflow-x: auto; white-space: nowrap; padding: 0.5rem 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .subjects-horizontal h3 { font-size: 1rem; margin-bottom: 0.5rem; }
        .subjects-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
        .subject-pills { display: flex; gap: 0.8rem; flex-wrap: nowrap; }
        .subject-pill { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--card-alt-bg); color: var(--text-color); padding: 0.5rem 1.2rem; border-radius: 2rem; text-decoration: none; font-weight: 500; transition: all 0.2s; white-space: nowrap; }
        .subject-pill:hover { background: var(--accent); color: #1e293b; transform: translateY(-2px); }
        .profile-info { display: flex; gap: 1rem; flex-wrap: wrap; margin: 0.5rem 0; }
        .profile-info span { background: var(--card-alt-bg); padding: 0.3rem 0.8rem; border-radius: 2rem; font-size: 0.85rem; }
        .group-card { margin: 0 0 20px 0; }
        .card-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .card-buttons a, .card-buttons .btn { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.9rem; background: var(--card-alt-bg); color: var(--text-color); border-radius: 2rem; font-size: 0.8rem; font-weight: 500; text-decoration: none; transition: all 0.25s ease; border: 1px solid transparent; }
        .card-buttons a:hover, .card-buttons .btn:hover { background: var(--accent); color: #1e293b; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(212,175,55,0.2); }
        .progress-bar { background: rgba(0,0,0,0.05); border-radius: 1rem; padding: 0.75rem; margin: 1rem 0; }
        .progress-fill { background: var(--accent); height: 0.6rem; border-radius: 1rem; transition: width 0.3s; }
        .warning { background: #fffbeb; border-left: 4px solid var(--warning); padding: 0.8rem 1rem; border-radius: 0.5rem; margin: 0.75rem 0; }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin: 1rem 0; }
        .card { background: var(--card-bg); border-radius: 1rem; padding: 1.5rem; box-shadow: var(--card-shadow); transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1); border: 1px solid rgba(0,0,0,0.02); }
        .card:hover { transform: translateY(-8px); box-shadow: var(--hover-shadow); border-color: rgba(212,175,55,0.15); }
        .card i { display: block; font-size: 2.2rem; color: var(--accent); margin-bottom: 0.5rem; text-align: center; background: rgba(212,175,55,0.08); width: 70px; height: 70px; line-height: 70px; border-radius: 50%; margin-left: auto; margin-right: auto; }
        .card h3 { margin-bottom: 0.75rem; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 1.5rem; }
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem 1.6rem; font-size: 1rem; font-weight: 600; border-radius: 3rem; border: none; cursor: pointer; transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1); background: var(--primary-medium); color: white; }
        .btn-secondary:hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem 1.6rem; font-size: 1rem; font-weight: 600; border-radius: 3rem; border: none; cursor: pointer; transition: all 0.25s cubic-bezier(0.2, 0.9, 0.4, 1); background: var(--accent); color: #1e293b; }
        .btn:hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3); filter: brightness(0.95); }
        .btn-danger { background: var(--error); color: white; }
        .btn-danger:hover { box-shadow: 0 4px 20px rgba(220, 38, 38, 0.4); }
    </style>
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php if ($user['consent_signed'] == 1): ?>
    <div style="position: relative; margin: 0.5rem 0 0.5rem 0.5rem; display: inline-block;">
        <button onclick="toggleProgressTracker()" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
            📍 Show Progress Tracker
        </button>
    </div>
    <script>
        let trackerVisible = false;
        function toggleProgressTracker() {
            const tracker = document.querySelector('.progress-tracker');
            const indicator = document.querySelector('.progress-indicator');
            if (tracker) {
                if (trackerVisible) {
                    tracker.style.display = 'none';
                    if (indicator) indicator.style.display = 'none';
                    document.querySelector('button[onclick="toggleProgressTracker()"]').textContent = '📍 Show Progress Tracker';
                } else {
                    tracker.style.display = 'flex';
                    if (indicator) indicator.style.display = 'block';
                    document.querySelector('button[onclick="toggleProgressTracker()"]').textContent = '📍 Hide Progress Tracker';
                }
                trackerVisible = !trackerVisible;
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const tracker = document.querySelector('.progress-tracker');
            const indicator = document.querySelector('.progress-indicator');
            if (tracker) {
                tracker.style.display = 'none';
                if (indicator) indicator.style.display = 'none';
            }
        });
    </script>
<?php endif; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>

    <div class="subjects-horizontal">
        <h3><i class="fas fa-book-open"></i> Your Subjects</h3>
        <div class="subjects-scroll">
            <?php if (!empty($subjects)): ?>
                <div class="subject-pills">
                    <?php foreach ($subjects as $subject): ?>
                        <a href="subject.php?subject=<?= urlencode($subject) ?>" class="subject-pill">
                            <i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($subject) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No subjects unlocked yet. Please wait for admin to unlock content.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-info">
        <span><i class="fas fa-user"></i> <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
        <span><i class="fas fa-chalkboard"></i> Class: <?= htmlspecialchars($user['class_level'] ?? '') ?></span>
        <span><i class="fas fa-shield-alt"></i> Status: <?= ucfirst($user['status'] ?? '') ?></span>
    </div>

    <?php if ($group): ?>
    <div class="card group-card">
        <h3><i class="fas fa-users"></i> My Group: <?= htmlspecialchars($group['class_level'] ?? '') ?> – Group <?= $group['group_number'] ?? '' ?></h3>
        <p><strong>Fellow members:</strong></p>
        <ul>
        <?php foreach ($fellow_members as $f): ?>
            <li><?= htmlspecialchars($f['fullname'] ?? '') ?> (<?= htmlspecialchars($f['phone'] ?? '') ?>)</li>
        <?php endforeach; ?>
        <?php if (empty($fellow_members)) echo "<li>You are the first member of this group.</li>"; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-dumbbell"></i>
            <div class="stat-number"><?= $done_exercises ?>/<?= $total_exercises ?></div>
            <div class="stat-label">Exercises</div>
            <div class="stat-sub">Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-question-circle"></i>
            <div class="stat-number"><?= $done_quizzes ?>/<?= $total_quizzes ?></div>
            <div class="stat-label">Quizzes</div>
            <div class="stat-sub">Completed</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar-alt"></i>
            <div class="stat-number"><?= $attendance_rate ?>%</div>
            <div class="stat-label">Attendance (30d)</div>
            <div class="stat-sub"><?= $att_total ?> sessions</div>
        </div>
        <div class="stat-card">
            <div class="notif-icon-wrap">
                <i class="fas fa-envelope"></i>
                <span class="notif-badge <?= $unread_msgs == 0 ? 'zero' : '' ?>">
                    <?= $unread_msgs ?>
                </span>
            </div>
            <div class="stat-number"><?= $total_msgs ?></div>
            <div class="stat-label">Messages</div>
            <div class="stat-sub"><?= $unread_msgs > 0 ? $unread_msgs . ' unread' : 'All read ✓' ?></div>
        </div>
    </div>

    <div class="progress-bar">
        <strong>📚 Exercise Progress:</strong> <?= $done_exercises ?>/<?= $total_exercises ?> completed (<?= $total_exercises ? round(($done_exercises/$total_exercises)*100) : 0 ?>%)<br>
        <div class="progress-fill" style="width:<?= $total_exercises ? round(($done_exercises/$total_exercises)*100) : 0 ?>%"></div>
    </div>
    <div class="progress-bar">
        <strong>📊 Quiz Progress:</strong> <?= $done_quizzes ?>/<?= $total_quizzes ?> completed (<?= $total_quizzes ? round(($done_quizzes/$total_quizzes)*100) : 0 ?>%)<br>
        <div class="progress-fill" style="width:<?= $total_quizzes ? round(($done_quizzes/$total_quizzes)*100) : 0 ?>%"></div>
    </div>

    <?php if ($paper_count > 0): ?>
        <div class="warning">
            ⚠️ You have <?= $paper_count ?> exercise(s) that you promised to submit on paper. 
            <a href="pending_exercises.php">View pending</a>
        </div>
    <?php endif; ?>

    <div class="content-grid">
        <div class="card"><i class="fas fa-book"></i><h3>Books (PDF)</h3><div class="card-buttons"><a href="library.php">View Books</a></div></div>
        <div class="card"><i class="fas fa-folder-open"></i><h3>Subjects</h3><div class="card-buttons"><a href="subjects.php">Browse Subjects</a></div></div>
        <div class="card"><i class="fas fa-pen-alt"></i><h3>Exams</h3><div class="card-buttons"><a href="exams.php">Take Exams</a></div></div>
        <div class="card"><i class="fas fa-chart-line"></i><h3>Results</h3><div class="card-buttons"><a href="results.php">Check Results</a></div></div>
        <div class="card"><i class="fas fa-tasks"></i><h3>Assignments</h3><div class="card-buttons"><a href="assignments.php">Submit</a></div></div>
        <div class="card"><i class="fas fa-calendar-check"></i><h3>Attendance</h3><div class="card-buttons"><a href="attendance.php">View</a></div></div>
    </div>

    <div class="content-grid">
        <div class="card">
            <i class="fas fa-share-alt"></i>
            <h3>Share Resource</h3>
            <p>Upload a book, past paper, or useful note.</p>
            <div class="card-buttons"><a href="share_resource.php">📤 Share Resource</a></div>
        </div>
        <div class="card">
            <i class="fas fa-list-alt"></i>
            <h3>My Resources</h3>
            <p>Track your submitted resources.</p>
            <div class="card-buttons"><a href="my_resources.php">📋 View My Resources</a></div>
        </div>
        <div class="card">
            <i class="fas fa-brain"></i>
            <h3>Self‑Assessment Quiz</h3>
            <p>Test your knowledge with instant feedback.</p>
            <div class="card-buttons"><a href="select_subject_quiz.php">📝 Take Self‑Quiz</a></div>
        </div>
        <div class="card">
            <i class="fas fa-file-signature"></i>
            <h3>My Consent</h3>
            <p>View or download your signed agreement.</p>
            <div class="card-buttons"><a href="view_consent.php">📄 View Consent</a></div>
        </div>
        <div class="card">
            <i class="fas fa-star"></i>
            <h3>Testimonial</h3>
            <p>Share your experience with SMART Circle.</p>
            <div class="card-buttons"><a href="submit_testimonial.php">✍️ Write Testimonial</a></div>
        </div>
        <div class="card">
            <i class="fas fa-users"></i>
            <h3>My Group</h3>
            <p>See group members and meeting times.</p>
            <div class="card-buttons"><a href="my_group.php">👥 View Group</a></div>
        </div>
    </div>
</div>
<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>