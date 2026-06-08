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

$conn = getDB();
$uid = $_SESSION['user_id'];

// Fetch user (needed before check_access.php)
$stmt = $conn->prepare("SELECT approved, consent_signed, class_level, status FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    session_write_close();
    header("Location: login.php");
    exit;
}

require_once 'check_access.php';

$application = $conn->query("SELECT status, admin_notes FROM applications WHERE user_id=$uid")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Approval Status – SMART Circle</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="container">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="card">
        <h2><i class="fas fa-user-check"></i> Admin Approval Status</h2>
        <?php if ($user['approved'] == 1): ?>
            <div class="success">
                <p><strong>✅ Congratulations! Your application has been approved.</strong></p>
                <p>You may now proceed to sign the consent agreement and start your journey.</p>
                <a href="consent.php" class="btn">Sign Consent Form</a>
            </div>
        <?php elseif ($user['approved'] == 0 && isset($application['status']) && $application['status'] == 'rejected'): ?>
            <div class="error">
                <p><strong>❌ Your application has been rejected.</strong></p>
                <p>Reason: <?= htmlspecialchars($application['admin_notes'] ?? 'No specific reason provided.') ?></p>
                <p>If you believe this is a mistake, please contact the admin directly.</p>
                <a href="student_message.php" class="btn">Contact Admin</a>
            </div>
        <?php else: ?>
            <div class="warning">
                <p><strong>⏳ Your application is still pending review.</strong></p>
                <p>Please check back later. You will be notified once the admin makes a decision.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>