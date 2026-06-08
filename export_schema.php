<?php
// ===== EXPORT ALL TABLES TO A TEXT FILE =====
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once 'config.php';

$output_file = __DIR__ . '/database_schema_export.txt';
$handle = fopen($output_file, 'w');

if (!$handle) {
    die("❌ Could not create output file. Check permissions.");
}

fwrite($handle, "========================================\n");
fwrite($handle, "DATABASE SCHEMA EXPORT\n");
fwrite($handle, "Database: " . DB_NAME . "\n");
fwrite($handle, "Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "========================================\n\n");

$conn = getDB();

// Get all tables
$tables = $conn->query("SHOW TABLES");
$table_count = 0;

while ($row = $tables->fetch_row()) {
    $table_name = $row[0];
    $table_count++;
    
    fwrite($handle, "--------------------------------------------------\n");
    fwrite($handle, "TABLE: $table_name\n");
    fwrite($handle, "--------------------------------------------------\n");
    
    // Get CREATE TABLE statement
    $create_stmt = $conn->query("SHOW CREATE TABLE `$table_name`");
    $create_row = $create_stmt->fetch_assoc();
    fwrite($handle, $create_row['Create Table'] . ";\n\n");
    
    // Get row count
    $count_stmt = $conn->query("SELECT COUNT(*) as count FROM `$table_name`");
    $count = $count_stmt->fetch_assoc()['count'];
    fwrite($handle, "-- Row count: $count\n\n");
    
    // Get table engine and collation (optional extra info)
    $status_stmt = $conn->query("SHOW TABLE STATUS LIKE '$table_name'");
    $status = $status_stmt->fetch_assoc();
    if ($status) {
        fwrite($handle, "-- Engine: " . $status['Engine'] . "\n");
        fwrite($handle, "-- Collation: " . $status['Collation'] . "\n");
        fwrite($handle, "-- Data length: " . $status['Data_length'] . " bytes\n\n");
    }
}

fwrite($handle, "========================================\n");
fwrite($handle, "Total tables exported: $table_count\n");
fwrite($handle, "========================================\n");

fclose($handle);

echo "<h1>✅ Database Schema Exported</h1>";
echo "<p>Total tables: <strong>$table_count</strong></p>";
echo "<p><a href='database_schema_export.txt' download>📥 Download schema file</a></p>";
echo "<p>Upload this file to me for analysis.</p>";
echo "<p>File location on server: <code>" . $output_file . "</code></p>";
?>