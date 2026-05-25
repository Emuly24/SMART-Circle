<?php
// auth_check.php – Handles user status checks (approval, consent, suspension)
// Include this file AFTER cookie_login.php on student pages

// Already have $user from cookie_login.php
$user = $GLOBALS['auth_user'] ?? null;
if (!$user) {
    // If no user data is found, redirect to login
    header("Location: login.php");
    exit;
}

$conn = getDB();
$user_id = $user['id'];
$current = basename($_SERVER['SCRIPT_NAME']);
$always_allowed = ['index.php', 'logout.php', 'profile.php', 'notifications.php', 'change_password.php'];

// --- 1. NOT APPROVED (no application) → FORCED TO APPLY.PHP ALWAYS ---
if (!$user['approved']) {
    $has_application = $conn->query("SELECT id FROM applications WHERE user_id = $user_id")->num_rows > 0;
    
    if (!$has_application) {
        // Strict enforcement: only allow apply.php (plus basic pages)
        $allowed = array_merge($always_allowed, ['apply.php']);
        if (!in_array($current, $allowed)) {
            header("Location: apply.php");
            exit;
        }
    } else {
        // Has application but not approved → only pending.php and approval_status.php
        $allowed = array_merge($always_allowed, ['pending.php', 'approval_status.php']);
        if (!in_array($current, $allowed)) {
            header("Location: pending.php");
            exit;
        }
    }
    return;
}

// --- 2. APPROVED BUT CONSENT NOT SIGNED → must go to consent.php ---
if (!$user['consent_signed']) {
    $allowed = array_merge($always_allowed, ['consent.php']);
    if (!in_array($current, $allowed)) {
        header("Location: consent.php");
        exit;
    }
    return;
}

// --- 3. SUSPENDED / DISMISSED ---
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) {
        die('<!DOCTYPE html><html><head><title>Suspended</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Account Suspended</h1><p>You are suspended until ' . $end . '. Contact the admin.</p><a href="logout.php" class="btn-danger">Logout</a></div></div></body></html>');
    } else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
        // Update the global user data
        $GLOBALS['auth_user']['status'] = 'active';
        $GLOBALS['auth_user']['suspension_end'] = null;
    }
}
if ($user['status'] == 'dismissed') {
    die('<!DOCTYPE html><html><head><title>Dismissed</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Access Denied</h1><p>You have been dismissed from SMART Circle.</p><a href="logout.php" class="btn-danger">Logout</a></div></div></body></html>');
}

// --- 4. FULLY APPROVED AND CONSENT SIGNED → full access ---
// No restrictions – allow all pages
return;
?>