<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$conn = getDB();
$user_id = $_SESSION['user_id'];

// Fetch user data FIRST
$stmt = $conn->prepare("SELECT approved, consent_signed, status, suspension_end, class_level FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Now set the session variable safely
$_SESSION['class_level'] = $user['class_level'];

if (!$user['approved']) {
    // Not approved – dashboard will show apply link; no block
}
if (!$user['consent_signed']) {
    header("Location: consent.php");
    exit;
}
if ($user['status'] == 'suspended') {
    $end = $user['suspension_end'];
    if ($end && $end >= date('Y-m-d')) {
        die("<!DOCTYPE html><html><head><title>Suspended</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Account Suspended</h1></div><div class='error'>You are suspended until $end. Contact admin.</div><a href='logout.php'>Logout</a></div></body></html>");
    } else {
        $conn2 = getDB();
        $conn2->query("UPDATE users SET status='active', suspension_end=NULL WHERE id=$user_id");
    }
}
if ($user['status'] == 'dismissed') {
    die("<!DOCTYPE html><html><head><title>Dismissed</title><link rel='stylesheet' href='style.css'></head><body><div class='container'><div class='header'><h1>Access Denied</h1></div><div class='error'>You have been dismissed.</div><a href='logout.php'>Logout</a></div></body></html>");
}
?>