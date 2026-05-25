<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];

$conn = getDB();
$backup = "-- SMART Circle Backup\n-- " . date('Y-m-d H:i:s') . "\n\n";
$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_array()) {
    $table = $t[0];
    $create = $conn->query("SHOW CREATE TABLE $table")->fetch_assoc();
    $backup .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";
    $rows = $conn->query("SELECT * FROM $table");
    while ($row = $rows->fetch_assoc()) {
        $cols = array_keys($row);
        $vals = array_map([$conn, 'real_escape_string'], array_values($row));
        $backup .= "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES ('" . implode("', '", $vals) . "');\n";
    }
    $backup .= "\n";
}
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
echo $backup;
exit;