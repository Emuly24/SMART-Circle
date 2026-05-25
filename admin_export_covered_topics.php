<?php
require_once 'check_remember_me.php';

require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}
$conn = getDB();
$class = $_GET['class'] ?? '';
$where = ($class && $class != 'all') ? "WHERE class_level='$class'" : "";
$res = $conn->query("SELECT subject, topic, class_level, covered_date FROM topics_covered $where ORDER BY covered_date DESC");
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="covered_topics_'.date('Y-m-d').'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Subject', 'Topic', 'Class', 'Date Covered']);
while ($r = $res->fetch_assoc()) fputcsv($out, [$r['subject'], $r['topic'], $r['class_level'], $r['covered_date']]);
fclose($out);
exit;