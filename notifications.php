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

// Handle "Mark all as read"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $stmt = $conn->prepare("UPDATE admin_messages SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
}

// Handle individual "Mark as read"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_one'])) {
    $msg_id = (int)$_POST['mark_one'];
    $stmt = $conn->prepare("UPDATE admin_messages SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL");
    $stmt->bind_param("ii", $msg_id, $uid);
    $stmt->execute();
}

// Fetch messages
$messages_stmt = $conn->prepare("SELECT * FROM admin_messages WHERE user_id = ? ORDER BY sent_at DESC");
$messages_stmt->bind_param("i", $uid);
$messages_stmt->execute();
$messages = $messages_stmt->get_result();

// Check if user has been approved
$user_stmt = $conn->prepare("SELECT approved FROM users WHERE id = ?");
$user_stmt->bind_param("i", $uid);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$approved = $user['approved'] ?? 0;

// Fetch student details for the dynamic message
$app_stmt = $conn->prepare("
    SELECT u.fullname, u.class_level, g.group_number
    FROM users u
    LEFT JOIN group_members gm ON u.id = gm.user_id
    LEFT JOIN groups g ON gm.group_id = g.id
    WHERE u.id = ?
");
$app_stmt->bind_param("i", $uid);
$app_stmt->execute();
$app_data = $app_stmt->get_result()->fetch_assoc();

$fullname = $app_data['fullname'] ?? 'Student';
$class = $app_data['class_level'] ?? '';
$group_number = $app_data['group_number'] ?? 'Not assigned';
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Notifications – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="notification-page">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>

    <div class="container">
        <?php if ($approved): ?>
            <div class="card success-card">
                <h2><i class="fas fa-user-check"></i> Congratulations, <?= htmlspecialchars($fullname) ?>!</h2>
                <p>Your application has been reviewed and approved by the SMART Circle admin team.</p>
                
                <?php if ($group_number !== 'Not assigned'): ?>
                    <p>You have been assigned to <strong><?= htmlspecialchars($class) ?> – Group <?= htmlspecialchars($group_number) ?></strong>. Get ready to meet your fellow learners!</p>
                <?php else: ?>
                    <p>You have been assigned to a group. Please check your dashboard for the group details.</p>
                <?php endif; ?>
                
                <p><strong>Next step:</strong> Please proceed to the <a href="consent.php">Consent Form</a> to confirm your commitments before accessing the full dashboard.</p>
            </div>
        <?php endif; ?>

        <?php if ($messages->num_rows == 0): ?>
            <div class="card"><p>No messages yet.</p></div>
        <?php else: ?>
            <?php while($m = $messages->fetch_assoc()): ?>
                <div class="card <?= !$m['read_at'] ? 'new-message' : '' ?>">
                    <strong><?= date('d M Y H:i', strtotime($m['sent_at'])) ?></strong><br>
                    <?= nl2br(htmlspecialchars($m['message'])) ?>
                    <?php if (!$m['read_at']): ?>
                        <form method="post" class="mark-one-form">
                            <button type="submit" name="mark_one" value="<?= $m['id'] ?>" class="btn-small">✔️ Mark as Read</button>
                        </form>
                    <?php else: ?>
                        <br><small><em>Read</em></small>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            <form method="post" class="mark-all-form">
                <button type="submit" name="mark_all" class="btn-secondary">✔️ Mark All as Read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>