<?php
session_save_path('/tmp');
session_start();

if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    echo "✅ Session found! The server works!";
} else {
    echo "❌ Session NOT found. The problem is your InfinityFree server configuration.";
}
?>