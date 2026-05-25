<?php

<?php
require_once 'config.php';
require_once 'cookie_login.php';

logoutUser();
header("Location: login.php");
exit;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_destroy();
header("Location: index.php");
exit; 