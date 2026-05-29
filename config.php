<?php
// Fix for InfinityFree HTTPS → Cookie mismatch
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true
    ]);
} else {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true
    ]);
}
session_save_path('/tmp');

// ---------- DATABASE (InfinityFree) ----------
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_NAME', 'if0_41797522_smarttutor');
define('DB_USER', 'if0_41797522');
define('DB_PASS', 'Emuly241295');

define('ADMIN_EMAIL', 'blessingsemulyn@gmail.com');
define('CSRF_SECRET', '224d11095174e2966eb60fdda5127cbe09d6d357e52fa80e0a297953a6e04f95');
define('SESSION_SECRET', 'a96d548496c8f7f85220fa458e49d297a3bcdf78fcf546b71999161a9cd87851');

function getDB() {
    static $conn = null;
    if ($conn !== null && !@$conn->ping()) $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}
function log_activity($user_id, $action, $details = null) {
    $conn = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $details = $conn->real_escape_string($details);
    $conn->query("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES ($user_id, '$action', '$details', '$ip')");
}

// Get admin hash from database (fallback to hardcoded if table missing)
function getAdminHash() {
    static $hash = null;
    if ($hash !== null) return $hash;
    $conn = getDB();
    $result = $conn->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'admin_hash'");
    if ($result && $row = $result->fetch_assoc()) {
        $hash = $row['setting_value'];
    } else {
        // Fallback default (smarttutor@2026)
        $hash = '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu';
    }
    return $hash;
}

// Group lock helper
function is_content_unlocked($content_type, $content_id, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;
    $conn = getDB();
    $group = $conn->query("SELECT group_id FROM group_members WHERE user_id = $user_id")->fetch_assoc();
    if (!$group) return false;
    $group_id = $group['group_id'];
    $lock = $conn->query("SELECT is_locked FROM group_content_locks 
        WHERE group_id = $group_id AND content_type = '$content_type' AND content_id = $content_id")->fetch_assoc();
    if (!$lock) {
        $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES ($group_id, '$content_type', $content_id, 1)");
        return false;
    }
    return $lock['is_locked'] == 0;
}
?>