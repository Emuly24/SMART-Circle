<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
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