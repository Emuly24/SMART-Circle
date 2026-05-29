<?php
require_once 'config.php';
session_save_path('/tmp');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CRITICAL FIX: If user is already logged in, redirect immediately
if (isset($_SESSION['user_id'])) {
    // Redirect based on role (admin goes to admin_dashboard, student to dashboard)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];

    if (empty($login) || empty($pass)) {
        $error = "Enter phone/email and password.";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            
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
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['consent_signed'] = $user['consent_signed'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['suspension_end'] = $user['suspension_end'];
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