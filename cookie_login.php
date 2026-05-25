<?php
// cookie_login.php – Secure cookie-based authentication for InfinityFree
// Include this file at the top of EVERY protected page (including admin pages)

require_once 'config.php';

function loginUser($user_id, $role, $remember = false) {
    // Generate a secure random token
    $token = bin2hex(random_bytes(32));
    $expires = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+12 hours'));
    $cookie_expiry = $remember ? time() + 86400 * 30 : 0;
    
    $conn = getDB();
    // Delete any old tokens for this user
    $conn->query("DELETE FROM login_tokens WHERE user_id = $user_id");
    // Insert the new token
    $conn->query("INSERT INTO login_tokens (user_id, token, role, expires) VALUES ($user_id, '$token', '$role', '$expires')");
    
    // Set the cookie
    setcookie('auth_token', $token, $cookie_expiry, '/', '', false, true);
    
    return true;
}

function logoutUser() {
    $token = $_COOKIE['auth_token'] ?? '';
    if ($token) {
        $conn = getDB();
        $conn->query("DELETE FROM login_tokens WHERE token = '$token'");
    }
    setcookie('auth_token', '', time() - 3600, '/', '', false, true);
    return true;
}

function checkLogin() {
    $token = $_COOKIE['auth_token'] ?? '';
    if (empty($token)) {
        return null;
    }
    
    $conn = getDB();
    $result = $conn->query("SELECT user_id, role FROM login_tokens WHERE token = '$token' AND expires > NOW()");
    if ($result && $row = $result->fetch_assoc()) {
        return $row; // Returns ['user_id' => X, 'role' => 'admin' or 'student']
    }
    return null;
}

function getLoggedInUser() {
    $login = checkLogin();
    if (!$login) return null;
    
    $conn = getDB();
    $result = $conn->query("SELECT * FROM users WHERE id = {$login['user_id']}");
    if ($result) {
        return $result->fetch_assoc();
    }
    return null;
}

// Called on every page load before any output
function enforceLogin() {
    $login = checkLogin();
    $current = basename($_SERVER['SCRIPT_NAME']);
    $public_pages = ['index.php', 'login.php', 'signup.php', 'logout.php'];
    
    if (!$login && !in_array($current, $public_pages)) {
        header("Location: login.php");
        exit;
    }
    
    if ($login) {
        $role = $login['role'];
        $user = getLoggedInUser();
        if (!$user) {
            logoutUser();
            header("Location: login.php");
            exit;
        }
        
        // Store user data in superglobals for easy access
        $GLOBALS['auth_user'] = $user;
        $GLOBALS['auth_role'] = $role;
        
        // Role-based redirects
        if ($role === 'admin' && strpos($current, 'admin_') !== 0) {
            // Admin trying to access student page → send to admin dashboard
            header("Location: admin_dashboard.php");
            exit;
        }
        if ($role === 'student' && strpos($current, 'admin_') === 0) {
            // Student trying to access admin page → send to dashboard
            header("Location: dashboard.php");
            exit;
        }
    }
}

// Run enforceLogin automatically
enforceLogin();
?>