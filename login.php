<?php
ob_start();
require_once 'check_remember_me.php';
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in as student, show welcome
if (isset($_SESSION['user_id'])) {
    // ... existing welcome code ...
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($login) || empty($pass)) {
        $error = "Enter username/phone and password.";
    } else {
        $conn = getDB();
        // ✅ UPDATED: Now checks phone OR username (no email)
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR username = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            // ✅ Admin login
            if (isset($user['role']) && $user['role'] === 'admin') {
                $_SESSION['admin_logged'] = true;
                $_SESSION['role'] = 'admin';
                $_SESSION['fullname'] = $user['fullname'];
                unset($_SESSION['user_id']);
                if (function_exists('log_activity')) {
                    log_activity($user['id'], "admin_login", "Admin logged in");
                }
                file_put_contents('login_debug.txt', "Admin logged in successfully. Redirecting to admin_dashboard.php\n", FILE_APPEND);
        header("Location: admin_dashboard.php");
        exit;
            }

            // ✅ Student login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = 'student';
            unset($_SESSION['admin_logged']);
            session_regenerate_id(true); // Prevents session hijacking and ensures fresh session data

            if (function_exists('log_activity')) {
                log_activity($user['id'], "login", "Logged in via login form");
            }
            

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $conn->query("DELETE FROM remember_tokens WHERE user_id = {$user['id']}");
                $stmt2 = $conn->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $user['id'], $token, $expires);
                $stmt2->execute();
                setcookie('remember_me', $token, time() + 86400 * 30, '/', '', false, true);
            }

            // Check approval and consent
            if ($user['approved'] == 0) {
                $has_app = $conn->query("SELECT id FROM applications WHERE user_id = {$user['id']}")->num_rows > 0;
                if (!$has_app) {
                    header("Location: apply.php");
                    exit;
                } else {
                    header("Location: pending.php");
                    exit;
                }
            } elseif ($user['approved'] == 1 && $user['consent_signed'] == 0) {
                header("Location: consent.php");
                exit;
            }

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Login - SMART Circle</title><link rel="stylesheet" href="style.css"></head>
<body class="login-page">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="login-container">
        <h2 class="login-title">Welcome Back</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="login">Username or Phone Number</label>
                <input type="text" id="login" name="login" required placeholder="Enter your username or phone">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            <div class="form-group">
                <label class="distinct-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Remember Me</span>
                </label>
            </div>
            <button type="submit" class="btn btn-login">Login</button>
        </form>
        <div class="login-links">
            <a href="signup.php">Don't have an account? Sign up here</a>
            <a href="forgot_password.php">Forgot password?</a>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>
<?php ob_end_flush(); ?>