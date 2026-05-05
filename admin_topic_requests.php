<?php
require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}
$conn = getDB();

if (isset($_GET['mark_covered'])) {
    $subject = $_GET['subject'];
    $topic = $_GET['topic'];
    $class = $_GET['class'];
    $conn->query("INSERT INTO topics_covered (subject,topic,class_level,covered_date) VALUES ('$subject','$topic','$class',CURDATE())");
    header("Location: admin_topic_requests.php");
    exit;
}
if (isset($_GET['clear_class'])) {
    $class = $_GET['clear_class'];
    $conn->query("DELETE FROM topic_requests WHERE class_level='$class'");
    header("Location: admin_topic_requests.php");
    exit;
}
$classes = ['Form 3', 'Form 4'];
?>
<!DOCTYPE html><html><head><title>Topic Requests</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h1>Topic Requests by Class</h1>
        <?php foreach($classes as $c): 
            $req = $conn->query("SELECT subject, topic, COUNT(*) as cnt FROM topic_requests WHERE class_level='$c' GROUP BY subject, topic ORDER BY cnt DESC");
        ?>
            <h2><?= $c ?> <a href="?clear_class=<?= $c ?>" onclick="return confirm('Clear all requests for <?= $c ?>?')" class="btn-danger" style="font-size:0.8rem;">Clear all</a></h2>
            <?php if($req->num_rows == 0): ?>
                <div class="card"><p>No topic requests for <?= $c ?>.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Subject</th><th>Topic</th><th>Requests</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while($r=$req->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['subject']) ?></td>
                            <td><?= htmlspecialchars($r['topic']) ?></td>
                            <td><?= $r['cnt'] ?></td>
                            <td><a href="?mark_covered=1&subject=<?= urlencode($r['subject']) ?>&topic=<?= urlencode($r['topic']) ?>&class=<?= $c ?>" onclick="return confirm('Mark as covered?')" class="btn-success">Mark Covered</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>