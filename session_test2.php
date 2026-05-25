<?php
session_save_path('/tmp');
session_start();
if (isset($_SESSION['test_admin'])) {
    echo "Session persisted!";
} else {
    echo "Session failed. Contact InfinityFree support: session.save_path issue.";
}
?>