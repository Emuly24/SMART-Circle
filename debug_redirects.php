<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
echo "<h1>🚨 REDIRECT LOOP DIAGNOSTIC – DEEP DB CHECK</h1>";
echo "<p>Run this file from your browser. If you see this text, PHP is working and no redirect is happening.</p>";
echo "<hr>";

// ========== 1. SESSION PATH & WRITE TEST ==========
echo "<h2>1. Session Storage</h2>";
$session_path = __DIR__ . '/sessions';
echo "Session path configured: <code>$session_path</code><br>";

if (!is_dir($session_path)) {
    $created = mkdir($session_path, 0755, true);
    echo "Folder did not exist. Created? " . ($created ? "✅ YES" : "❌ NO") . "<br>";
} else {
    echo "Folder exists ✅<br>";
}

// Test write permissions
$test_file = $session_path . '/test_write.tmp';
if (file_put_contents($test_file, 'test') !== false) {
    echo "Write permission: ✅ YES (can write to sessions folder)<br>";
    unlink($test_file);
} else {
    echo "Write permission: ❌ NO (cannot write to sessions folder - fix permissions)<br>";
}

// ========== 2. SESSION START & DATA TEST ==========
echo "<h2>2. Session Start & Data Persistence</h2>";
session_save_path($session_path);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['debug_test'] = time();
$test_value = $_SESSION['debug_test'] ?? null;
$session_file = $session_path . '/sess_' . session_id();

echo "Session ID: " . session_id() . "<br>";
echo "Session file exists: " . (file_exists($session_file) ? "✅ YES" : "❌ NO") . "<br>";
echo "Session data written: " . (isset($_SESSION['debug_test']) ? "✅ YES" : "❌ NO") . "<br>";
echo "Session data value: " . $test_value . "<br>";

// ========== 3. HTTPS & COOKIE SECURITY ==========
echo "<h2>3. HTTPS & Cookie Configuration</h2>";
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
echo "Current protocol: " . ($is_https ? "✅ HTTPS" : "❌ HTTP") . "<br>";

if ($is_https) {
    echo "You are on HTTPS – session cookies MUST be set to 'secure' to be sent by the browser.<br>";
} else {
    echo "You are on HTTP – session cookies should NOT be set to 'secure'.<br>";
}

$cookie_params = session_get_cookie_params();
echo "Session cookie parameters:<br>";
echo "- Lifetime: " . $cookie_params['lifetime'] . "<br>";
echo "- Path: " . $cookie_params['path'] . "<br>";
echo "- Domain: " . $cookie_params['domain'] . "<br>";
echo "- Secure: " . ($cookie_params['secure'] ? "✅ YES" : "❌ NO") . "<br>";
echo "- HttpOnly: " . ($cookie_params['httponly'] ? "✅ YES" : "❌ NO") . "<br>";
echo "- SameSite: " . $cookie_params['samesite'] . "<br>";

if ($is_https && !$cookie_params['secure']) {
    echo "<span style='color:red;font-weight:bold;'>❌ CRITICAL: HTTPS is active but 'secure' is false. Session cookies will be rejected by the browser causing a redirect loop.</span><br>";
}

// ========== 4. COOKIE RECEIVED ==========
echo "<h2>4. Browser Cookies Received</h2>";
echo "Cookie count: " . count($_COOKIE) . "<br>";
if (isset($_COOKIE['PHPSESSID'])) {
    echo "PHPSESSID cookie: ✅ YES (" . $_COOKIE['PHPSESSID'] . ")<br>";
} else {
    echo "PHPSESSID cookie: ❌ NO – Browser is not sending the session cookie.<br>";
}

// ========== 5. CONFIG.PHP LOAD ==========
echo "<h2>5. Config.php Load</h2>";
if (file_exists('config.php')) {
    echo "config.php exists ✅<br>";
    require_once 'config.php';
    echo "config.php loaded ✅<br>";
    echo "Database name: " . DB_NAME . "<br>";
} else {
    echo "❌ config.php missing!<br>";
}

// ========== 6. DEEP DATABASE CHECKS ==========
echo "<h2>6. Deep Database Checks</h2>";
$conn = getDB();

// 6.1. Check if tables exist
$required_tables = [
    'users',
    'applications',
    'group_members',
    'groups',
    'group_content_locks',
    'password_resets',
    'admin_messages',
    'student_resources',
    'testimonials',
    'attendance',
    'discipline_log',
    'books',
    'notes',
    'note_exercises',
    'assignments',
    'assignment_submissions',
    'exams',
    'exam_submissions',
    'exam_questions',
    'exam_answers',
    'quizzes',
    'quiz_questions',
    'quiz_attempts',
    'quiz_answers',
    'self_quizzes',
    'self_quiz_attempts',
    'topic_requests',
    'topics_covered',
    'book_questions',
    'book_annotations',
    'student_messages',
    'student_reports',
    'subject_questions',
    'activity_log'
];

echo "<h3>6.1. Required Tables</h3>";
foreach ($required_tables as $table) {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "✅ $table exists<br>";
    } else {
        echo "❌ $table MISSING<br>";
    }
}

// 6.2. User status check (for currently logged-in user)
echo "<h3>6.2. Current User Status</h3>";
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        echo "User found: " . htmlspecialchars($user['fullname']) . "<br>";
        echo "- Approved: " . ($user['approved'] ? "✅ YES" : "❌ NO") . "<br>";
        echo "- Consent signed: " . ($user['consent_signed'] ? "✅ YES" : "❌ NO") . "<br>";
        echo "- Status: " . $user['status'] . "<br>";
        echo "- Suspension end: " . ($user['suspension_end'] ?? 'None') . "<br>";
        echo "- Role: " . ($user['role'] ?? 'student') . "<br>";
        echo "- Class level: " . ($user['class_level'] ?? 'Not set') . "<br>";

        // Check if user has an application
        $app_stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = ?");
        $app_stmt->bind_param("i", $uid);
        $app_stmt->execute();
        $app = $app_stmt->get_result()->fetch_assoc();
        if ($app) {
            echo "- Application status: " . $app['status'] . "<br>";
            echo "- Admin notes: " . ($app['admin_notes'] ?? 'None') . "<br>";
        } else {
            echo "- Application: ❌ No application found<br>";
        }

        // Check group membership
        $group_stmt = $conn->prepare("SELECT g.id, g.group_number, g.class_level FROM group_members gm JOIN groups g ON gm.group_id = g.id WHERE gm.user_id = ?");
        $group_stmt->bind_param("i", $uid);
        $group_stmt->execute();
        $group = $group_stmt->get_result()->fetch_assoc();
        if ($group) {
            echo "- Group: " . $group['class_level'] . " – Group " . $group['group_number'] . "<br>";
        } else {
            echo "- Group: ❌ Not assigned to any group<br>";
        }
    } else {
        echo "❌ User with ID $uid not found in database.<br>";
    }
} else {
    echo "⚠️ No user logged in. Session user_id is not set.<br>";
    echo "This is normal if you are viewing this page while logged out.<br>";
}

// 6.3. Check for stale or orphaned session data in database (if using DB sessions)
echo "<h3>6.3. Database Session Tables</h3>";
$session_tables = ['sessions', 'login_tokens', 'remember_tokens'];
foreach ($session_tables as $table) {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "✅ $table exists<br>";
        // Count rows
        $count_stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $count_stmt->fetch_assoc()['count'];
        echo "  - Rows: $count<br>";
        if ($count > 100) {
            echo "  ⚠️ Large number of rows – consider clearing stale data.<br>";
        }
    } else {
        echo "❌ $table does not exist (likely not using database sessions)<br>";
    }
}

// 6.4. Check group_content_locks for current user
echo "<h3>6.4. Group Content Locks</h3>";
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $group_stmt = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
    $group_stmt->bind_param("i", $uid);
    $group_stmt->execute();
    $group = $group_stmt->get_result()->fetch_assoc();
    if ($group) {
        $group_id = $group['group_id'];
        $lock_stmt = $conn->prepare("SELECT content_type, content_id, is_locked FROM group_content_locks WHERE group_id = ?");
        $lock_stmt->bind_param("i", $group_id);
        $lock_stmt->execute();
        $locks = $lock_stmt->get_result();
        if ($locks->num_rows > 0) {
            while ($lock = $locks->fetch_assoc()) {
                echo "  - " . $lock['content_type'] . " ID " . $lock['content_id'] . ": " . ($lock['is_locked'] ? "🔒 LOCKED" : "✅ UNLOCKED") . "<br>";
            }
        } else {
            echo "  No locks found for your group.<br>";
        }
    } else {
        echo "  No group assignment found.<br>";
    }
} else {
    echo "  Not logged in – skipping group lock check.<br>";
}

// 6.5. Check for pending applications
echo "<h3>6.5. Pending Applications</h3>";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc()['count'];
echo "Pending applications: $pending<br>";

// 6.6. Check for suspended users
echo "<h3>6.6. Suspended Users</h3>";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'suspended'");
$stmt->execute();
$suspended = $stmt->get_result()->fetch_assoc()['count'];
echo "Suspended users: $suspended<br>";

// 6.7. Check database session handler (if used)
echo "<h3>6.7. Database Session Handler Check</h3>";
$handler_found = false;
if (function_exists('session_set_save_handler')) {
    echo "session_set_save_handler() exists – a custom session handler may be active.<br>";
} else {
    echo "No custom session handler detected.<br>";
}

// 6.8. Check for orphaned application records (user_id not in users)
echo "<h3>6.8. Orphaned Records</h3>";
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE u.id IS NULL");
$stmt->execute();
$orphans = $stmt->get_result()->fetch_assoc()['count'];
if ($orphans > 0) {
    echo "⚠️ Orphaned applications: $orphans (user deleted but application remains)<br>";
} else {
    echo "✅ No orphaned applications found.<br>";
}

// ========== 7. CHECK_ACCESS.PHP TEST ==========
echo "<h2>7. check_access.php Behavior</h2>";
if (file_exists('check_access.php')) {
    echo "check_access.php exists ✅<br>";
    // Simulate a user not logged in
    $temp_uid = $_SESSION['user_id'] ?? null;
    unset($_SESSION['user_id']);
    $temp_role = $_SESSION['role'] ?? null;
    unset($_SESSION['role']);
    
    $public_pages = ['index.php', 'signup.php', 'login.php', 'logout.php', 'debug_redirects.php'];
    $current = basename($_SERVER['SCRIPT_NAME']);
    if (in_array($current, $public_pages)) {
        echo "✅ Current page ($current) is in allowed public pages list.<br>";
    } else {
        echo "⚠️ Current page ($current) is NOT in public pages list – would redirect to login.php.<br>";
    }
    
    // Restore session
    if ($temp_uid !== null) $_SESSION['user_id'] = $temp_uid;
    if ($temp_role !== null) $_SESSION['role'] = $temp_role;
    
    echo "If your current page is NOT in the public list and you are not logged in, check_access.php redirects to login.php.<br>";
} else {
    echo "❌ check_access.php missing.<br>";
}

// ========== 8. LOGIN.PHP BEHAVIOR ==========
echo "<h2>8. login.php Behavior (Simulated)</h2>";
echo "If you are already logged in (session user_id exists), login.php should redirect you to dashboard.php.<br>";
if (isset($_SESSION['user_id'])) {
    echo "✅ You ARE currently logged in (user_id: " . $_SESSION['user_id'] . "). login.php would redirect to dashboard.<br>";
    echo "This is likely your redirect loop: you are logged in → login.php redirects to dashboard → dashboard sees no session (because of cookie issue) → redirects back to login.php → loop.<br>";
} else {
    echo "✅ You are NOT currently logged in. login.php would show the login form.<br>";
}

// ========== 9. DASHBOARD.PHP BEHAVIOR ==========
echo "<h2>9. dashboard.php Behavior (Simulated)</h2>";
if (isset($_SESSION['user_id'])) {
    echo "If you go to dashboard.php while logged in, it should show the dashboard.<br>";
    echo "If dashboard.php does NOT find your session, it will redirect to login.php.<br>";
    echo "This is the other side of the loop: dashboard → login → dashboard → login.<br>";
} else {
    echo "If you are NOT logged in, dashboard.php redirects to login.php (normal behavior).<br>";
}

// ========== 10. SESSION FILE VERIFICATION ==========
echo "<h2>10. Session File Verification</h2>";
echo "Session file path: " . session_save_path() . "/sess_" . session_id() . "<br>";
if (file_exists(session_save_path() . "/sess_" . session_id())) {
    $size = filesize(session_save_path() . "/sess_" . session_id());
    echo "Session file exists ✅ (size: $size bytes)<br>";
    if ($size > 10) {
        echo "Session file has content ✅<br>";
    } else {
        echo "Session file is empty ❌ – data not being written.<br>";
    }
} else {
    echo "Session file does not exist ❌ – session data not being saved to disk.<br>";
}

// ========== 11. RECOMMENDATIONS ==========
echo "<h2>11. Recommendations</h2>";
$has_error = false;

if ($is_https && !$cookie_params['secure']) {
    echo "<span style='color:red'>❌ FIX: Set 'secure' => true in session_set_cookie_params() in config.php</span><br>";
    $has_error = true;
}

if (!file_exists(session_save_path() . "/sess_" . session_id()) || !isset($_SESSION['debug_test'])) {
    echo "<span style='color:red'>❌ FIX: Check permissions on 'sessions' folder (755). Also check that session_save_path() is called BEFORE session_start().</span><br>";
    $has_error = true;
}

if (!isset($_COOKIE['PHPSESSID'])) {
    echo "<span style='color:red'>❌ FIX: Your browser is not sending the session cookie. This usually means 'secure' flag is wrong (see above) or cookie lifetime is 0 with HTTPS issues.</span><br>";
    $has_error = true;
}

// Additional database recommendations
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    // Check if user is approved but consent not signed
    $user_check = $conn->prepare("SELECT approved, consent_signed FROM users WHERE id = ?");
    $user_check->bind_param("i", $uid);
    $user_check->execute();
    $user_data = $user_check->get_result()->fetch_assoc();
    if ($user_data) {
        if ($user_data['approved'] && !$user_data['consent_signed']) {
            echo "<span style='color:orange'>⚠️ You are approved but have not signed consent. You will be redirected to consent.php.</span><br>";
        }
        if (!$user_data['approved']) {
            echo "<span style='color:orange'>⚠️ You are not approved. You will be redirected to apply.php or pending.php.</span><br>";
        }
    }
}

if (!$has_error) {
    echo "<span style='color:green;font-weight:bold;'>✅ No critical errors detected. Your redirect loop may be caused by .htaccess or a server-level issue.</span><br>";
    echo "<span style='color:orange'>Check your .htaccess for an HTTP → HTTPS redirect that may be causing the loop. Temporarily comment it out to test.</span><br>";
}

// ========== 12. FINAL ADVICE ==========
echo "<hr>";
echo "<h2>12. If loop persists after all checks</h2>";
echo "<p>Add this line to the TOP of login.php (after session_start):</p>";
echo "<code>if (isset($_SESSION['user_id'])) { var_dump($_SESSION); die(); }</code>";
echo "<p>This will stop the redirect and show you what's in the session.</p>";

echo "<hr>";
echo "<p>✅ This diagnostic file is complete. It does NOT redirect anywhere.</p>";
echo "<p>If you see this entire report, PHP is working and the loop is NOT in this file.</p>";
?>