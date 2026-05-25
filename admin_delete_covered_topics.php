<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class = $_POST['class_level'];
    $older = $_POST['older_than'];
    $where = [];
    $params = [];
    $types = "";
    if ($class && $class != 'all') { $where[] = "class_level=?"; $params[] = $class; $types .= "s"; }
    if ($older) { $where[] = "covered_date < ?"; $params[] = $older; $types .= "s"; }
    if (empty($where)) $msg = "Select a filter.";
    else {
        $sql = "DELETE FROM topics_covered WHERE " . implode(" AND ", $where);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $msg = "Deleted " . $stmt->affected_rows . " record(s).";
    }
}
?>
<!DOCTYPE html><html><head><title>Delete Covered Topics</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card"><h2>Batch Delete Covered Topics</h2>
        <?php if($msg) echo "<div class='success'>$msg</div>"; ?>
        <form method="post">
            <div class="form-group"><label>Class:</label><select name="class_level"><option>All</option><option>Form 3</option><option>Form 4</option></select></div>
            <div class="form-group"><label>Delete older than date:</label><input type="date" name="older_than" placeholder="dd/mm/yyyy"></div>
            <button type="submit" class="btn btn-delete">Delete</button>
        </form></div>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>