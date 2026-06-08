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

// Fetch group info
$group_stmt = $conn->prepare("SELECT g.id as group_id, g.group_number, g.class_level, g.route
    FROM group_members gm 
    JOIN groups g ON gm.group_id = g.id 
    WHERE gm.user_id = ?");
$group_stmt->bind_param("i", $uid);
$group_stmt->execute();
$group = $group_stmt->get_result()->fetch_assoc();

$fellow_members = [];
if ($group) {
    $fellow_stmt = $conn->prepare("SELECT u.fullname, u.phone 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = ? AND u.id != ?");
    $fellow_stmt->bind_param("ii", $group['group_id'], $uid);
    $fellow_stmt->execute();
    $fellow_result = $fellow_stmt->get_result();
    while ($f = $fellow_result->fetch_assoc()) {
        $fellow_members[] = $f;
    }
}

// Get today's meeting start time for this group
$today = date('Y-m-d');
$meeting = null;
if ($group) {
    $meeting_stmt = $conn->prepare("SELECT start_time FROM group_meetings WHERE group_id = ? AND meeting_date = ?");
    $meeting_stmt->bind_param("is", $group['group_id'], $today);
    $meeting_stmt->execute();
    $meeting = $meeting_stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Group - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    
    <div class="card" style="margin: 20px;">
        <h3><i class="fas fa-users"></i> My Group: <?= htmlspecialchars($group['class_level'] ?? 'Not assigned') ?> – Group <?= $group['group_number'] ?? '—' ?></h3>
        <p><strong>Route:</strong> <?= ucfirst($group['route'] ?? 'Not set') ?></p>
        
        <?php if ($meeting): ?>
            <div style="background: #f0f7ff; padding: 10px; border-radius: 8px; margin: 10px 0;">
                <p><strong>⏰ Today's Meeting:</strong> Starts at <?= date('h:i A', strtotime($meeting['start_time'])) ?></p>
                <?php
                $att_stmt = $conn->prepare("SELECT status, remarks FROM attendance WHERE user_id = ? AND date = ?");
                $att_stmt->bind_param("is", $uid, $today);
                $att_stmt->execute();
                $att = $att_stmt->get_result()->fetch_assoc();
                if ($att && $att['status'] == 'late' && empty($att['remarks'])): ?>
                    <p class="warning">You were marked late. Please <a href="attendance.php">submit your reason here</a>.</p>
                <?php elseif ($att && $att['status'] == 'late' && !empty($att['remarks'])): ?>
                    <p class="info">You were late. Your reason has been recorded.</p>
                <?php elseif ($att && $att['status'] == 'on_time'): ?>
                    <p class="success">✅ You were on time today. Thank you!</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No meeting scheduled for today.</p>
        <?php endif; ?>
        
        <p><strong>Fellow members:</strong></p>
        <ul>
        <?php if ($group && count($fellow_members) > 0): ?>
            <?php foreach ($fellow_members as $f): ?>
                <li>
                    <?= htmlspecialchars($f['fullname']) ?>
                    <a href="tel:<?= htmlspecialchars($f['phone']) ?>" class="btn-call" style="margin-left: 10px; background: #28a745; color: white; padding: 3px 8px; border-radius: 20px; text-decoration: none; font-size: 0.7rem;">
                        📞 Call
                    </a>
                </li>
            <?php endforeach; ?>
        <?php elseif ($group): ?>
            <li>You are the first member of this group.</li>
        <?php else: ?>
            <li>You are not assigned to any group yet. Please contact admin.</li>
        <?php endif; ?>
        </ul>
    </div>
    
   <?php include_once 'includes/footer.php'; ?>
   <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>