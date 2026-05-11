<?php
ob_start(); // Prevents HTTP 500/Headers already sent error
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $first_name = '';
    $fullname = $_SESSION['fullname'] ?? '';
    if (!empty($fullname)) {
        $name_parts = explode(' ', trim($fullname));
        $first_name = $name_parts[0] ?? '';
    }
    if (empty($first_name)) {
        $conn = getDB();
        $uid = (int)$_SESSION['user_id'];
        $result = $conn->query("SELECT fullname FROM users WHERE id = $uid");
        if ($result && $user = $result->fetch_assoc()) {
            $fullname = $user['fullname'] ?? '';
            $name_parts = explode(' ', trim($fullname));
            $first_name = $name_parts[0] ?? '';
        }
    }
    if (empty($first_name)) {
        $first_name = 'User';
    }
    ?>
    <!DOCTYPE html>
    <html><head><title>Already Logged In</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="container">
        <div class="card">
            <h2>Welcome back, <?= htmlspecialchars($first_name) ?>!</h2>
            <p>We wish you a joyful and meaningful use of SMART Circle.</p>
            <div class="card-buttons" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($login) || empty($pass)) {
        $error = "Enter phone/email and password.";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];

            if (function_exists('log_activity')) {
                log_activity($user['id'], "login", "Logged in via login form");
            }

            if (isset($user['role']) && $user['role'] === 'admin') {
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_logged'] = true;
                unset($_SESSION['user_id']);
                header("Location: admin_dashboard.php");
                exit;
            } else {
                $_SESSION['role'] = 'student';
                unset($_SESSION['admin_logged']);
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['consent_signed'] = $user['consent_signed'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['suspension_end'] = $user['suspension_end'];
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
                <label for="login">Phone Number or Email</label>
                <input type="text" id="login" name="login" required placeholder="Enter your phone or email">
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
            <a href="signup.php">Don’t have an account? Sign up here</a>
            <a href="forgot_password.php">Forgot password?</a>
        </div>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
</body>
</html>
<?php ob_end_flush(); ?>