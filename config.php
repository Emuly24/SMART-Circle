<?php
// ===== DATABASE SESSION HANDLER – FIX FOR INFINITYFREE =====
// Must be the FIRST PHP CODE in this file.

// ===== ERROR REPORTING =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== DATABASE CONSTANTS =====
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_NAME', 'if0_41797522_smarttutor');
define('DB_USER', 'if0_41797522');
define('DB_PASS', 'Emuly241295');

define('ADMIN_EMAIL', 'blessingsemulyn@gmail.com');
define('CSRF_SECRET', '224d11095174e2966eb60fdda5127cbe09d6d357e52fa80e0a297953a6e04f95');
define('SESSION_SECRET', 'a96d548496c8f7f85220fa458e49d297a3bcdf78fcf546b71999161a9cd87851');

// ===== DATABASE CONNECTION FUNCTION =====
function getDB() {
    static $conn = null;
    if ($conn !== null && !@$conn->ping()) $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            die("Database connection failed. Please try again later.");
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ===== SESSION HANDLER FUNCTIONS =====
function sess_open($savePath, $sessionName) {
    return true;
}

function sess_close() {
    return true;
}

function sess_read($id) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT data FROM sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['data'];
    }
    return '';
}

function sess_write($id, $data) {
    $conn = getDB();
    $stmt = $conn->prepare("REPLACE INTO sessions (id, data, last_accessed) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $id, $data);
    return $stmt->execute();
}

function sess_destroy($id) {
    $conn = getDB();
    $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    return $stmt->execute();
}

function sess_gc($maxlifetime) {
    $conn = getDB();
    $stmt = $conn->prepare("DELETE FROM sessions WHERE last_accessed < NOW() - INTERVAL ? SECOND");
    $stmt->bind_param("i", $maxlifetime);
    return $stmt->execute();
}

// ===== REGISTER THE HANDLER =====
session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');

// ===== START SESSION =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== REST OF YOUR FUNCTIONS =====
function log_activity($user_id, $action, $details = null) {
    $conn = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $details = $details ?? '';
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
}

function getAdminHash() {
    static $hash = null;
    if ($hash !== null) return $hash;
    $conn = getDB();
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'admin_hash'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $hash = $row['setting_value'];
    } else {
        $hash = password_hash('smarttutor@2026', PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('admin_hash', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt2->bind_param("ss", $hash, $hash);
        $stmt2->execute();
    }
    return $hash;
}

function is_content_unlocked($content_type, $content_id, $user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;
    $conn = getDB();
    $stmt = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    if (!$group) return false;
    $group_id = $group['group_id'];
    $stmt2 = $conn->prepare("SELECT is_locked FROM group_content_locks WHERE group_id = ? AND content_type = ? AND content_id = ?");
    $stmt2->bind_param("isi", $group_id, $content_type, $content_id);
    $stmt2->execute();
    $lock = $stmt2->get_result()->fetch_assoc();
    if (!$lock) {
        $stmt3 = $conn->prepare("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES (?, ?, ?, 1)");
        $stmt3->bind_param("isi", $group_id, $content_type, $content_id);
        $stmt3->execute();
        return false;
    }
    return $lock['is_locked'] == 0;
}
?>