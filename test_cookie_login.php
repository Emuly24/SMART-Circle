<?php
// cookie_debug.php – Shows cookie status WITHOUT automatic redirect

// We manually include the cookie login logic without running enforceLogin()
require_once 'config.php';
require_once 'cookie_login.php';

// Override enforceLogin() to do nothing for this debug file
function enforceLogin() {
    // Do nothing – we are debugging
}

// Now check the cookie manually
$token = $_COOKIE['auth_token'] ?? '';
if ($token) {
    $conn = getDB();
    $result = $conn->query("SELECT * FROM login_tokens WHERE token = '$token'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "✅ Valid token found in database.<br>";
        echo "User ID: " . $row['user_id'] . "<br>";
        echo "Role: " . $row['role'] . "<br>";
        echo "Expires: " . $row['expires'] . "<br>";
    } else {
        echo "❌ Token exists in cookie but NOT in database (invalid or expired).<br>";
        echo "Please log out and log in again.<br>";
    }
} else {
    echo "❌ No auth_token cookie found.<br>";
}

echo "<br>COOKIE array:<br>";
print_r($_COOKIE);
echo "<br><br>Login form: <a href='login.php'>Go to login page</a>";
?>