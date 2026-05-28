<?php
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $user['id'];
$class = $user['class_level'];
<!DOCTYPE html><html><head><title>Covered Topics</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>

    
<div class="container"><div class="content-grid"><?php if($covered->num_rows==0) echo "<p>None yet.</p>"; else{ while($c=$covered->fetch_assoc()):?><div class="card"><strong><?=htmlspecialchars($c['subject'])?>:</strong> <?=htmlspecialchars($c['topic'])?><br><small>Covered: <?=$c['covered_date']?></small></div><?php endwhile; }?></div><?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body></html>