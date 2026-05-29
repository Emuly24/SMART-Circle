<?php
require_once 'check_remember_me.php';
<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = $_SESSION['user_id'];
$conn = getDB();
$user = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
?>

// List diagrams from uploads/diagrams/ directory
$diagramsDir = __DIR__ . '/uploads/diagrams/';
$result = [];

if (is_dir($diagramsDir)) {
    $files = glob($diagramsDir . '*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
    foreach ($files as $file) {
        $result[] = [
            'url' => 'uploads/diagrams/' . basename($file),
            'name' => basename($file),
            'size' => filesize($file)
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($result);
?>