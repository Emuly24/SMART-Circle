<?php
require_once 'check_remember_me.php';
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

 if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit;
}

$conn = getDB();

// Helper: Get all table names
function getTables($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    return $tables;
}

// Helper: Get table schema
function getTableSchema($conn, $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_assoc();
    return $row['Create Table'] . ";\n\n";
}

// Helper: Get table data as INSERT statements
function getTableData($conn, $table) {
    $output = "";
    $result = $conn->query("SELECT * FROM `$table`");
    if ($result->num_rows == 0) return $output;
    
    $columns = [];
    $col_result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($col = $col_result->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    $col_str = "`" . implode("`, `", $columns) . "`";
    
    while ($row = $result->fetch_assoc()) {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = "NULL";
            } else {
                $values[] = "'" . $conn->real_escape_string($value) . "'";
            }
        }
        $output .= "INSERT INTO `$table` ($col_str) VALUES (" . implode(", ", $values) . ");\n";
    }
    return $output;
}

// Helper: Recursive file listing
function listFiles($dir, $base = '', $exclude = []) {
    $files = [];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        if (in_array($item, $exclude)) continue;
        $full = $dir . '/' . $item;
        $rel = $base . '/' . $item;
        if (is_file($full)) {
            $files[] = $rel . ' (' . round(filesize($full) / 1024, 2) . ' KB)';
        } elseif (is_dir($full)) {
            $files = array_merge($files, listFiles($full, $rel, $exclude));
        }
    }
    return $files;
}

// Main backup generation
$output = "";
$output .= "============================================================\n";
$output .= "  SMART CIRCLE FULL BACKUP\n";
$output .= "  Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "  Database: " . DB_NAME . "\n";
$output .= "============================================================\n\n";

// ===== DATABASE SECTION =====
$output .= "===== DATABASE SCHEMA AND DATA =====\n\n";

$tables = getTables($conn);
foreach ($tables as $table) {
    $output .= "----- Table: $table -----\n";
    $output .= getTableSchema($conn, $table);
    $output .= getTableData($conn, $table);
    $output .= "\n";
}

// ===== FILE LISTING SECTION =====
$output .= "===== FILE LISTING =====\n\n";
$root = __DIR__;
$exclude = ['admin_backup_extract_all.php']; // exclude self
$all_files = listFiles($root, '.', $exclude);

foreach ($all_files as $file) {
    $output .= $file . "\n";
}

// Write to file
$filename = 'backup_' . date('Ymd_His') . '.txt';
file_put_contents($filename, $output);

echo "<!DOCTYPE html><html><head><title>Backup Complete</title><link rel='stylesheet' href='style.css'></head><body>";
echo "<div class='container'><div class='card' style='padding:2rem; text-align:center;'>";
echo "<h2>✅ Full Backup Created</h2>";
echo "<p>Backup file created: <strong>$filename</strong></p>";
echo "<p>Size: " . round(filesize($filename) / 1024, 2) . " KB</p>";
echo "<div style='margin: 1rem 0;'><a href='$filename' class='btn' download>📥 Download Backup File</a></div>";
echo "<p><a href='admin_dashboard.php' class='btn-secondary'>← Back to Dashboard</a></p>";
echo "</div></div></body></html>";
?>