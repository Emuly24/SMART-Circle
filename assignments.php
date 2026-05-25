<?php
require_once 'config.php';
require_once 'cookie_login.php';
require_once 'check_access.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];

$conn = getDB();
$uid = $user['id'];
$class = $user['class_level'];

$assignments = $conn->query("SELECT a.*, n.title as note_title
    FROM assignments a 
    LEFT JOIN notes n ON a.note_id = n.id
    WHERE a.class_level='$class' 
    AND EXISTS (SELECT 1 FROM group_content_locks gcl 
                WHERE gcl.content_type='assignment' AND gcl.content_id=a.id 
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id=$uid) 
                AND gcl.is_locked = 0)
    ORDER BY a.due_date");
?>
<!DOCTYPE html><html><head><title>Assignments</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <?php if($assignments->num_rows == 0): ?>
                <div class="card"><p>No assignments available yet.</p></div>
            <?php else: ?>
                <?php while($a=$assignments->fetch_assoc()): 
                    $submitted = $conn->query("SELECT submitted_at, marks FROM assignment_submissions WHERE assignment_id={$a['id']} AND user_id=$uid")->fetch_assoc();
                ?>
                <div class="card">
                    <h3><?=htmlspecialchars($a['title'])?> (<?=$a['subject']?>)</h3>
                    <p><?=nl2br(htmlspecialchars($a['description']))?></p>
                    
                    <?php if($a['attachment_file_path']): ?>
                        <p><a href='admin_download.php?type=assignment&file=<?=urlencode(basename($a['attachment_file_path']))?>' target='_blank'>📎 Download attachment</a></p>
                    <?php endif; ?>
                    
                    <?php if($a['note_id']): ?>
                        <p><small>📖 From: <a href="student_view_note.php?id=<?=$a['note_id']?>" class="text-link"><?=htmlspecialchars($a['note_title'] ?? 'Note')?></a></small></p>
                    <?php endif; ?>
                    
                    <p>Due: <?=date('F j, Y g:i A', strtotime($a['due_date']))?></p>
                    
                    <?php if($submitted): ?>
                        <p>✅ Submitted on <?=date('F j, Y g:i A', strtotime($submitted['submitted_at']))?>. 
                        Marks: <?=($submitted['marks'] !== null) ? $submitted['marks'] : 'Pending'?></p>
                    <?php else: ?>
                        <div class="card-buttons">
                            <a href="submit_assignment.php?assignment_id=<?=$a['id']?>" class="btn">Submit</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        <div class="footer">
            <a href="dashboard.php" class="btn-back">← Back</a>
        </div>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>