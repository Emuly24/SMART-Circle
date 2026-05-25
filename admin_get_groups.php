<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();
$class = $_GET['class'] ?? '';
$route = $_GET['route'] ?? '';
if (!$class || !$route) {
    echo json_encode([]);
    exit;
}

$groups = $conn->query("
    SELECT 
        g.id, 
        g.group_number,
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS current_members,
        (SELECT COUNT(*) FROM attendance a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'on_time' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) AS on_time_count,
        (SELECT COUNT(*) FROM attendance a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'late' AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) AS late_count,
        (SELECT COUNT(DISTINCT a.user_id) FROM exercise_attempts a 
            JOIN group_members gm ON a.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND a.status = 'paper_pending'
        ) AS pending_exercises,
        (SELECT AVG(qa.points_awarded) FROM quiz_answers qa 
            JOIN quiz_attempts qat ON qa.attempt_id = qat.id 
            JOIN group_members gm ON qat.user_id = gm.user_id 
            WHERE gm.group_id = g.id AND qat.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) AS avg_quiz_score
    FROM groups g 
    WHERE g.class_level = '$class' AND g.route = '$route' 
    ORDER BY g.group_number
");

$result = [];
while ($g = $groups->fetch_assoc()) {
    $total_days = $g['on_time_count'] + $g['late_count'];
    $attendance_rate = $total_days > 0 ? round(($g['on_time_count'] / $total_days) * 100, 1) : 0;
    $g['attendance_rate'] = $attendance_rate;
    $g['avg_quiz_score'] = round($g['avg_quiz_score'] ?? 0, 1);
    $result[] = $g;
}
header('Content-Type: application/json');
echo json_encode($result);
?>