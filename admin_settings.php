<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $_POST['new_password'];
    $conf = $_POST['confirm_password'];
    if (strlen($new) < 5) {
        $msg = "Password must be at least 5 characters.";
    } elseif ($new !== $conf) {
        $msg = "Passwords do not match.";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE admin_settings SET setting_value = '$new_hash' WHERE setting_key = 'admin_hash'");
        $msg = "Password updated. Use the new password on next login.";
    }
}
?>
<!DOCTYPE html>
<html><head><title>Admin Settings</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2>Change Admin Password</h2>
            <?php if ($msg): ?>
                <div class="success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>New Password (min 5 characters)</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        </div>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>