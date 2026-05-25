<?php
// check_access.php – Handles user status checks (approval, consent, suspension)

// Ensure $user is available
if (basename($_SERVER['SCRIPT_NAME']) === 'login.php') {
    return; // Stop redirect on login page
}
if (!isset($user)) {
    $user = $GLOBALS['auth_user'] ?? null;
}
if (!$user) {
    header("Location: login.php");
    exit;
}

$conn = getDB();
$user_id = $user['id'];
$current = basename($_SERVER['SCRIPT_NAME']);
$always_allowed = ['index.php', 'logout.php', 'profile.php', 'notifications.php', 'change_password.php'];

// --- 1. NOT APPROVED ---
if (!$user['approved']) {
    $has_application = $conn->query("SELECT id FROM applications WHERE user_id = $user_id")->num_rows > 0;
    if (!$has_application) {
        $allowed = array_merge($always_allowed, ['apply.php']);
        if (!in_array($current, $allowed)) {
            header("Location: apply.php");
            exit;
        }
    } else {
        $allowed = array_merge($always_allowed, ['pending.php', 'approval_status.php']);
        if (!in_array($current, $allowed)) {
            header("Location: pending.php");
            exit;
        }
    }
    return;
}

// --- 2. CONSENT NOT SIGNED ---
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
        die('... suspended message ...');
    } else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
        $GLOBALS['auth_user']['status'] = 'active';
        $GLOBALS['auth_user']['suspension_end'] = null;
    }
}
if ($user['status'] == 'dismissed') {
    die('... dismissed message ...');
}

// --- 4. FULL ACCESS ---
return;