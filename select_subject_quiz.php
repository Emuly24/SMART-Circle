<?php
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$subjects = $conn->query("SELECT DISTINCT subject FROM self_quizzes ORDER BY subject");
?>
<!DOCTYPE html>
<html><head><title>Choose Subject for Self‑Quiz</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div class="card">
        <h2>Select a Subject</h2>
        <div class="content-grid">
            <?php while($s = $subjects->fetch_assoc()): ?>
                <a href="self_quiz.php?subject=<?= urlencode($s['subject']) ?>" class="btn" style="margin:5px;"><?= htmlspecialchars($s['subject']) ?></a>
            <?php endwhile; ?>
        </div>
    </div>
   <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>