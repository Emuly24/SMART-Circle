<?php
require_once 'config.php';

$admin_hash = getAdminHash();
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Circle Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();
$type = $_GET['type'] ?? '';
if ($type === 'equations') {
    $result = $conn->query("SELECT id, title, latex, category FROM equations_library ORDER BY category, title");
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
} elseif ($type === 'diagrams') {
    $result = $conn->query("SELECT id, title, file_path, category FROM diagrams_library ORDER BY category, title");
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
} else {
    echo json_encode([]);
}
?>