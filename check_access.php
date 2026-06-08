<?php
// ===== CHECK ACCESS – Must be included AFTER session_start() and user fetch =====

// Prevent direct execution
if (!isset($_SESSION['user_id'])) {
    // Allow public pages
    $public_pages = ['index.php', 'signup.php', 'login.php', 'logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME']);
    if (!in_array($current, $public_pages)) {
        session_write_close();
        header("Location: login.php");
        exit;
    }
    return;
}

// User is logged in – fetch their status
$conn = getDB();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT approved, consent_signed, status, suspension_end, class_level FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    session_write_close();
    header("Location: login.php");
    exit;
}

// Store session variables
$_SESSION['class_level'] = $user['class_level'];
$_SESSION['approved'] = $user['approved'];
$_SESSION['consent_signed'] = $user['consent_signed'];
$_SESSION['status'] = $user['status'];

$current = basename($_SERVER['SCRIPT_NAME']);
$always_allowed = ['index.php', 'logout.php', 'profile.php', 'notifications.php', 'change_password.php'];

// --- 1. NOT APPROVED (no application) → FORCED TO APPLY.PHP ALWAYS ---
if (!$user['approved']) {
    $has_application = $conn->query("SELECT id FROM applications WHERE user_id = $user_id")->num_rows > 0;
    if (!$has_application) {
        $allowed = array_merge($always_allowed, ['apply.php']);
        if (!in_array($current, $allowed)) {
            session_write_close();
            header("Location: apply.php");
            exit;
        }
    } else {
        $allowed = array_merge($always_allowed, ['pending.php', 'approval_status.php']);
        if (!in_array($current, $allowed)) {
            session_write_close();
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
        session_write_close();
        header("Location: consent.php");
        exit;
    }
    return;
}

// --- 3. SUSPENDED / DISMISSED ---
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) {
        session_write_close();
        die('<!DOCTYPE html><html><head><title>Suspended</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Account Suspended</h1><p>You are suspended until ' . $end . '. Contact the admin.</p><a href="logout.php" class="btn-danger">Logout</a></div></div></body></html>');
    } else {
        $conn->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
        $_SESSION['status'] = 'active';
    }
}
if ($user['status'] == 'dismissed') {
    session_write_close();
    die('<!DOCTYPE html><html><head><title>Dismissed</title><link rel="stylesheet" href="style.css"></head><body><div class="container"><div class="card error"><h1>Access Denied</h1><p>You have been dismissed from SMART Circle.</p><a href="logout.php" class="btn-danger">Logout</a></div></div></body></html>');
}

// --- 4. FULLY APPROVED AND CONSENT SIGNED → full access ---
return;
?>