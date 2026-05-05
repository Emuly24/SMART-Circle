<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "<h2>SMART Tutor Admin System Check</h2>";

// 1. Test config loading
echo "<h3>1. Config & Functions</h3>";
require_once 'config.php';
echo "✅ config.php loaded.<br>";
if (function_exists('getDB')) echo "✅ getDB() exists.<br>";
else { echo "❌ getDB() missing!<br>"; exit; }
if (function_exists('getAdminHash')) echo "✅ getAdminHash() exists.<br>";
else { echo "⚠️ getAdminHash() missing – will use fallback.<br>"; }

// 2. Test database connection
echo "<h3>2. Database Connection</h3>";
$conn = getDB();
echo "✅ Database connected.<br>";

// 3. Test required tables
echo "<h3>3. Required Tables</h3>";
$tables = ['users', 'applications', 'groups', 'group_members', 'admin_settings'];
foreach ($tables as $t) {
    $result = $conn->query("SHOW TABLES LIKE '$t'");
    if ($result && $result->num_rows) echo "✅ Table `$t` exists.<br>";
    else echo "❌ Table `$t` missing!<br>";
}

// 4. Test admin_settings content
echo "<h3>4. Admin Settings</h3>";
$res = $conn->query("SELECT * FROM admin_settings WHERE setting_key = 'admin_hash'");
if ($res && $row = $res->fetch_assoc()) {
    echo "✅ admin_hash found: " . substr($row['setting_value'], 0, 20) . "...<br>";
} else {
    echo "❌ admin_hash not found! Run migration SQL.<br>";
}

// 5. Test a few key queries from failing pages
echo "<h3>5. Query Tests (simulate each page)</h3>";

// admin_approve.php – pending applications
$pending = $conn->query("SELECT COUNT(*) FROM applications WHERE status='pending'");
if ($pending) echo "✅ Pending applications query works. Count: " . $pending->fetch_row()[0] . "<br>";
else echo "❌ Pending applications query failed: " . $conn->error . "<br>";

// admin_assignments_list.php – assignments
$assign = $conn->query("SELECT COUNT(*) FROM assignments");
if ($assign) echo "✅ Assignments query works. Count: " . $assign->fetch_row()[0] . "<br>";
else echo "❌ Assignments query failed: " . $conn->error . "<br>";

// admin_notes_list.php – notes
$notes = $conn->query("SELECT COUNT(*) FROM notes");
if ($notes) echo "✅ Notes query works. Count: " . $notes->fetch_row()[0] . "<br>";
else echo "❌ Notes query failed: " . $conn->error . "<br>";

// admin_exams_list.php – exams
$exams = $conn->query("SELECT COUNT(*) FROM exams");
if ($exams) echo "✅ Exams query works. Count: " . $exams->fetch_row()[0] . "<br>";
else echo "❌ Exams query failed: " . $conn->error . "<br>";

// admin_topic_requests.php – topic_requests
$topic = $conn->query("SELECT COUNT(*) FROM topic_requests");
if ($topic) echo "✅ Topic requests query works. Count: " . $topic->fetch_row()[0] . "<br>";
else echo "❌ Topic requests query failed: " . $conn->error . "<br>";

// admin_topics_covered.php – topics_covered
$covered = $conn->query("SELECT COUNT(*) FROM topics_covered");
if ($covered) echo "✅ Topics covered query works. Count: " . $covered->fetch_row()[0] . "<br>";
else echo "❌ Topics covered query failed: " . $conn->error . "<br>";

// admin_discipline_log.php – discipline_log
$disc = $conn->query("SELECT COUNT(*) FROM discipline_log");
if ($disc) echo "✅ Discipline log query works. Count: " . $disc->fetch_row()[0] . "<br>";
else echo "❌ Discipline log query failed: " . $conn->error . "<br>";

// admin_feedback.php – student_messages
$msg = $conn->query("SELECT COUNT(*) FROM student_messages");
if ($msg) echo "✅ Student messages query works. Count: " . $msg->fetch_row()[0] . "<br>";
else echo "❌ Student messages query failed: " . $conn->error . "<br>";

// admin_reports.php – student_reports
$reports = $conn->query("SELECT COUNT(*) FROM student_reports");
if ($reports) echo "✅ Student reports query works. Count: " . $reports->fetch_row()[0] . "<br>";
else echo "❌ Student reports query failed: " . $conn->error . "<br>";

// admin_upload_book.php – books table (insert not tested)
$books = $conn->query("SELECT COUNT(*) FROM books");
if ($books) echo "✅ Books query works. Count: " . $books->fetch_row()[0] . "<br>";
else echo "❌ Books query failed: " . $conn->error . "<br>";

echo "<h3>6. Additional Checks</h3>";
echo "PHP version: " . phpversion() . "<br>";
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "active" : "not active") . "<br>";
echo "Memory limit: " . ini_get('memory_limit') . "<br>";
echo "Max execution time: " . ini_get('max_execution_time') . "<br>";

echo "<hr><strong>If any ❌ appears above, that is the cause of the blank pages.</strong>";
?>