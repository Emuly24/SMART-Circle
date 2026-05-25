<?php
// diagnose_redirects.php – Find the source of the redirect loop
// Run this file directly from your browser

echo "<h1>🔍 Redirect Loop Diagnostic</h1>";

// Save current output buffer
ob_start();

// Test 1: Check config.php
echo "<h2>1. Testing config.php</h2>";
require_once 'config.php';
echo "✅ config.php loaded successfully<br>";

// Test 2: Check cookie_login.php
echo "<h2>2. Testing cookie_login.php</h2>";
if (file_exists('cookie_login.php')) {
    require_once 'cookie_login.php';
    echo "✅ cookie_login.php loaded successfully<br>";
    
    // Check if enforceLogin() is being called
    echo "Checking enforceLogin()...<br>";
    $login = checkLogin();
    if ($login) {
        echo "✅ User is logged in: user_id={$login['user_id']}, role={$login['role']}<br>";
    } else {
        echo "❌ No user logged in. Cookie may be missing or expired.<br>";
    }
} else {
    echo "❌ cookie_login.php not found<br>";
}

// Test 3: Check check_access.php
echo "<h2>3. Testing check_access.php</h2>";
if (file_exists('check_access.php')) {
    // Temporarily disable redirects
    require_once 'check_access.php';
    echo "✅ check_access.php loaded successfully<br>";
    echo "Current page: " . basename($_SERVER['SCRIPT_NAME']) . "<br>";
} else {
    echo "❌ check_access.php not found<br>";
}

// Test 4: Check if any file is sending redirect headers
echo "<h2>4. Checking for redirect headers</h2>";
$headers = headers_list();
if (count($headers) > 0) {
    echo "Headers already sent:<br>";
    foreach ($headers as $header) {
        if (strpos($header, 'Location:') !== false) {
            echo "❌ Redirect detected: " . $header . "<br>";
        } else {
            echo "✅ " . $header . "<br>";
        }
    }
} else {
    echo "✅ No headers sent yet<br>";
}

// Test 5: Check if any function is modifying the session or redirecting
echo "<h2>5. Function call trace</h2>";

// List all available functions related to login
$functions = get_defined_functions();
$login_functions = [];
foreach ($functions['user'] as $func) {
    if (strpos($func, 'login') !== false || strpos($func, 'auth') !== false || strpos($func, 'check') !== false) {
        $login_functions[] = $func;
    }
}

echo "Available login-related functions: " . implode(', ', $login_functions) . "<br>";

// Test 6: Check if login.php exists and what it does
echo "<h2>6. Checking login.php</h2>";
if (file_exists('login.php')) {
    echo "✅ login.php found<br>";
    $login_content = file_get_contents('login.php');
    if (strpos($login_content, 'header("Location:') !== false) {
        echo "⚠️ login.php contains redirects<br>";
    }
    if (strpos($login_content, 'require_once \'check_access.php\'') !== false) {
        echo "⚠️ login.php includes check_access.php which may redirect<br>";
    }
    if (strpos($login_content, 'require_once \'cookie_login.php\'') !== false) {
        echo "⚠️ login.php includes cookie_login.php which may redirect<br>";
    }
} else {
    echo "❌ login.php not found<br>";
}

// Test 7: Check if any file has session_start() without a guard
echo "<h2>7. Checking for session_start() without guard</h2>";
$files = glob('*.php');
$session_files = [];
foreach ($files as $file) {
    if ($file === 'diagnose_redirects.php') continue;
    $content = file_get_contents($file);
    if (strpos($content, 'session_start()') !== false) {
        $session_files[] = $file;
    }
}
if (count($session_files) > 0) {
    echo "⚠️ These files call session_start(): " . implode(', ', $session_files) . "<br>";
} else {
    echo "✅ No session_start() found in any file<br>";
}

// Test 8: Check if any file is using header() without exit
echo "<h2>8. Checking for header() without exit</h2>";
$header_files = [];
foreach ($files as $file) {
    if ($file === 'diagnose_redirects.php') continue;
    $content = file_get_contents($file);
    if (strpos($content, 'header(') !== false && strpos($content, 'exit;') === false) {
        $header_files[] = $file;
    }
}
if (count($header_files) > 0) {
    echo "⚠️ These files may have header() without exit: " . implode(', ', $header_files) . "<br>";
} else {
    echo "✅ All header() calls appear to have exit;<br>";
}

echo "<hr>";
echo "<h2>✅ Diagnostics complete.</h2>";
echo "If you see any '❌' or '⚠️' messages above, those are the areas to fix.<br>";
echo "If no errors are shown, the loop is likely caused by a .htaccess redirect or a server-level issue.";

// Clean the buffer to prevent any unintended output
ob_end_clean();

// Output the diagnostic results
echo $output;
?>