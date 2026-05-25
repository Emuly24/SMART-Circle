<?php
session_save_path('/tmp');
session_start();

$_SESSION['admin_logged'] = true;
echo "✅ Session set. <a href='session_test2.php'>Click here to go to test 2</a>";
?>