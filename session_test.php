<?php
session_save_path('/tmp');
session_start();
$_SESSION['test_admin'] = 'exists';
echo "Session set. <a href='session_test2.php'>Go to test 2</a>";
?>