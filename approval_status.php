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
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];
$user = $conn->query("SELECT approved, consent_signed, class_level, status FROM users WHERE id=$uid")->fetch_assoc();
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
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>