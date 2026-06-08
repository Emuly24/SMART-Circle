<?php
// ===== FORCED DEBUG – SHOW EVERYTHING =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ===== LOG FILE – track every step =====
$log_file = __DIR__ . '/debug_login.log';
function debug_log($msg) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}
debug_log("=== START LOGIN.PHP ===");

// ===== SESSION SETUP =====
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
    debug_log("Created sessions folder");
}
session_save_path($session_path);
debug_log("Session path set to: " . $session_path);

// ===== CHECK SESSION STATUS BEFORE START =====
debug_log("Session status before start: " . session_status());

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    debug_log("Session started");
}

// ===== CHECK SESSION DATA =====
debug_log("SESSION['user_id'] exists? " . (isset($_SESSION['user_id']) ? 'YES' : 'NO'));
debug_log("SESSION['role'] exists? " . (isset($_SESSION['role']) ? 'YES' : 'NO'));

// ===== LOOP BREAKER – if already logged in, redirect and exit =====
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    debug_log("User already logged in. Redirecting to dashboard.");
    session_write_close();
    debug_log("Redirecting to: " . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'));
    exit;
}

debug_log("User not logged in. Proceeding to login form.");

// ===== INCLUDE CONFIG =====
require_once 'config.php';
debug_log("Config loaded");

$error = '';

// ===== PROCESS POST REQUEST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST request received");
    
    $login = trim($_POST['login']);
    $pass = $_POST['password'];
    debug_log("Login: $login");

    if (empty($login) || empty($pass)) {
        $error = "Enter phone/email and password.";
        debug_log("Error: Empty login or password");
    } else {
        $conn = getDB();
        debug_log("DB connection established");

        $stmt = $conn->prepare("SELECT id, fullname, password, approved, consent_signed, status, suspension_end, role FROM users WHERE phone = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        debug_log("Query executed");

        $user = $stmt->get_result()->fetch_assoc();
        debug_log("User found? " . ($user ? 'YES' : 'NO'));

        if ($user && password_verify($pass, $user['password'])) {
            debug_log("Password verified for user ID: " . $user['id']);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            debug_log("Session variables set");

            if (isset($user['role']) && $user['role'] === 'admin') {
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_logged'] = true;
                debug_log("Admin role set");
            } else {
                $_SESSION['role'] = 'student';
                $_SESSION['approved'] = $user['approved'];
                $_SESSION['consent_signed'] = $user['consent_signed'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['suspension_end'] = $user['suspension_end'];
                debug_log("Student role set");
            }

            session_regenerate_id(true);
            debug_log("Session regenerated");

            session_write_close();
            debug_log("Session written to disk");

            // Dump session data to log
            debug_log("Session data after write: " . json_encode($_SESSION));

            // Do NOT redirect immediately – first check if the session was actually saved
            // ========== DEBUG – STOP HERE TO CHECK SESSION ==========
            echo "<h1>DEBUG – SESSION SAVED?</h1>";
            echo "<pre>";
            var_dump($_SESSION);
            echo "</pre>";
            echo "<p>Check <code>debug_login.log</code> for detailed steps.</p>";
            echo "<p><a href='dashboard.php'>Click here to go to dashboard manually</a></p>";
            exit; // STOP EXECUTION – so you can see if session data is there
        } else {
            $error = "Invalid credentials.";
            debug_log("Invalid credentials");
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - SMART Circle</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <?php include_once 'includes/header.php'; ?>
    <div class="login-container">
        <h2 class="login-title">Welcome Back</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php
        // ========== SHOW DEBUG INFO AT TOP OF FORM ==========
        echo "<div style='background:#f0f0f0; padding:10px; margin-bottom:20px; border:2px solid red;'>";
        echo "<strong>DEBUG INFO:</strong><br>";
        echo "Session path: " . session_save_path() . "<br>";
        echo "Session ID: " . session_id() . "<br>";
        echo "Session status: " . session_status() . "<br>";
        echo "Session file exists? " . (file_exists(session_save_path() . '/sess_' . session_id()) ? 'YES' : 'NO') . "<br>";
        echo "Cookies: ";
        var_dump($_COOKIE);
        echo "</div>";
        ?>
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
    <div class="footer">
        <a href="index.php" class="btn-back">← Back</a>
    </div>
</body>
</html>