<?php
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $user['id'];
$class = $user['class_level'];
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
if (!$subject) die("No subject specified.");

// All queries use bound parameters: $subject comes straight from the URL.
$uid = (int) $uid;
$group_lock = "AND EXISTS (SELECT 1 FROM group_content_locks gcl
                WHERE gcl.content_type = ? AND gcl.content_id = %s
                AND gcl.group_id = (SELECT group_id FROM group_members WHERE user_id = ?)
                AND gcl.is_locked = 0)";

// Fetch unlocked notes
$stmt = $conn->prepare("SELECT n.id, n.title, n.created_at
    FROM notes n
    WHERE n.class_level = ? AND n.subject = ?
    " . sprintf($group_lock, 'n.id') . "
    ORDER BY n.title");
$type = 'note';
$stmt->bind_param("sssi", $class, $subject, $type, $uid);
$stmt->execute();
$notes = $stmt->get_result();

// Fetch assignments
$stmt = $conn->prepare("SELECT a.id, a.title, a.due_date,
    (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND user_id = ?) AS submitted,
    (SELECT marks FROM assignment_submissions WHERE assignment_id = a.id AND user_id = ?) AS marks
    FROM assignments a
    WHERE a.class_level = ? AND a.subject = ?
    " . sprintf($group_lock, 'a.id') . "
    ORDER BY a.due_date");
$type = 'assignment';
$stmt->bind_param("iisssi", $uid, $uid, $class, $subject, $type, $uid);
$stmt->execute();
$assignments = $stmt->get_result();

// Fetch quizzes
$stmt = $conn->prepare("SELECT q.id, q.title, q.description, q.time_limit
    FROM quizzes q
    JOIN notes n ON q.note_id = n.id
    WHERE n.class_level = ? AND n.subject = ?
    " . sprintf($group_lock, 'q.id') . "
    ORDER BY q.title");
$type = 'quiz';
$stmt->bind_param("sssi", $class, $subject, $type, $uid);
$stmt->execute();
$quizzes = $stmt->get_result();
?>
<!DOCTYPE html>
<html><head><title><?= htmlspecialchars($subject) ?> - SMART Circle</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="content-grid">
            <!-- Notes Section -->
            <div class="card">
                <h3>📖 Notes</h3>
                <?php if($notes->num_rows): ?>
                    <?php while($n=$notes->fetch_assoc()):?>
                        <p><strong><?=htmlspecialchars($n['title'])?></strong><br><a href="student_view_note.php?id=<?=$n['id']?>">Read This Note</a></p>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No notes unlocked yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Assignments Section -->
            <div class="card">
                <h3>📋 Assignments</h3>
                <?php if($assignments->num_rows): ?>
                    <?php while($a=$assignments->fetch_assoc()):?>
                        <p><strong><?=htmlspecialchars($a['title'])?></strong> (Due: <?=$a['due_date']?>)<br>
                        <?php if($a['submitted']):?>
                            Submitted on <?=$a['submitted']?>
                            <?php if($a['marks']!==null) echo " - Marks: {$a['marks']}";?>
                        <?php else:?>
                            <a href="submit_assignment.php?assignment_id=<?=$a['id']?>">Submit Assignment</a>
                        <?php endif;?>
                        </p>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No assignments yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Quizzes Section -->
            <div class="card">
                <h3>📝 Quizzes</h3>
                <?php if($quizzes->num_rows): ?>
                    <?php while($q=$quizzes->fetch_assoc()): 
                        $attempt = $conn->query("SELECT status FROM quiz_attempts WHERE user_id=$uid AND quiz_id={$q['id']}")->fetch_assoc();
                        $link = ($attempt && $attempt['status']=='submitted') ? "quiz_results.php?quiz_id={$q['id']}" : "take_quiz.php?quiz_id={$q['id']}";
                        $text = ($attempt && $attempt['status']=='submitted') ? "View Results" : "Take Quiz";
                    ?>
                        <p><strong><?=htmlspecialchars($q['title'])?></strong><br><a href="<?=$link?>"><?=$text?></a></p>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No quizzes yet.</p>
                <?php endif; ?>
            </div>

            <!-- Request Help Card (uses new subject_questions table) -->
            <div class="card">
                <i class="fas fa-question-circle"></i>
                <h3>Need Extra Help?</h3>
                <p>If something is not clear, ask your specific question here. The admin will respond personally.</p>
                <div class="card-buttons">
                    <a href="request_help_subject.php?subject=<?= urlencode($subject) ?>" class="btn">Ask a Question</a>
                </div>
            </div>
        </div>

       <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>