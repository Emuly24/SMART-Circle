<?php
ob_start();
require_once 'config.php';
require_once 'cookie_login.php'; // Make sure cookie_login.php is included!

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = $_POST['login'];
    $pass = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($login_input) || empty($pass)) {
        $error = "Enter username/phone and password.";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR username = ?");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            
            // Determine role
            $role = (isset($user['role']) && $user['role'] === 'admin') ? 'admin' : 'student';
            
            // ✅ CALL loginUser() ONLY ONCE
            $token = loginUser($user['id'], $role, $remember);
            
            // The $cookie_expiry variable is handled inside loginUser() - do not use it here
            
            if (function_exists('log_activity')) {
                log_activity($user['id'], "login", "Logged in via login form");
            }
            
            // Redirect based on role
            if ($role === 'admin') {
                header("Location: admin_dashboard.php");
                exit;
            } else {
                // Student checks
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
            }
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
</body>
</html>
<?php ob_end_flush(); ?>