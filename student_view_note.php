<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

if (!is_content_unlocked('note', $note_id, $uid)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Content Locked</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card error">
            <h2>🔒 Content Locked</h2>
            <p>This note is not yet available for your group. Please wait until the admin unlocks it.</p>
            <div class="card-buttons">
                <a href="library.php" class="btn-back">← Back to Library</a>
            </div>
        </div>
    </div>
    <?php include_once 'includes/testimonial_prompt.php'; ?>
    </body></html>
    <?php
    exit;
}

if (function_exists('log_activity')) {
    log_activity($uid, "view_note", "Note ID: $note_id");
}

// --------------------- STUDENT EXERCISE HANDLING ---------------------
$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_digital'])) {
    $ex_id = (int)$_POST['exercise_id'];
    $answer_text = trim($_POST['answer_text'] ?? '');
    $file_path = null;
    if (isset($_FILES['answer_file']) && $_FILES['answer_file']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/exercises/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['answer_file']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','pdf','txt'];
        if (in_array(strtolower($ext), $allowed)) {
            $filename = "exercise_{$ex_id}_user_{$uid}_".time().".$ext";
            if (move_uploaded_file($_FILES['answer_file']['tmp_name'], $dir.$filename)) {
                $file_path = $dir.$filename;
            }
        }
    }
    if (empty($answer_text) && !$file_path) {
        $error = "Please provide an answer (text or file).";
    } else {
        $stmt = $conn->prepare("INSERT INTO exercise_attempts (exercise_id, user_id, answer_text, answer_file_path, status) 
            VALUES (?, ?, ?, ?, 'digital_pending')
            ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), answer_file_path = VALUES(answer_file_path), status = 'digital_pending', updated_at = NOW()");
        $stmt->bind_param("iiss", $ex_id, $uid, $answer_text, $file_path);
        $stmt->execute();
        $success = "Digital answer submitted! Admin will mark it soon.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_paper'])) {
    $ex_id = (int)$_POST['exercise_id'];
    $promised_at = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO exercise_attempts (exercise_id, user_id, status, promised_at) 
        VALUES ($ex_id, $uid, 'paper_pending', '$promised_at')
        ON DUPLICATE KEY UPDATE status = 'paper_pending', promised_at = '$promised_at', reminder_sent = 0, warning_sent = 0, suspended_for_exercise = 0");
    $success = "You have promised to submit this exercise on paper. You can continue reading. Please submit within 24 hours.";
    header("Location: student_view_note.php?id=$note_id&msg=paper_promised");
    exit;
}

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'paper_promised') $msg = "Thank you. Your promise to submit on paper has been recorded.";

// ===== GET THE NOTE CONTENT =====
$full_content = $note['content'];

// ===== PARSE EXERCISES FROM THE CONTENT =====
// Find all Exercise headings (h3 or h4 with "Exercise X" in them)
preg_match_all('/<h[34][^>]*>.*?Exercise\s+(\d+).*?<\/h[34]>/i', $full_content, $matches, PREG_OFFSET_CAPTURE);
$exercise_positions = $matches[0];
$exercise_numbers = $matches[1];

// Build a list of exercises in the order they appear in the content
$exercise_order = [];
foreach ($exercise_numbers as $index => $match) {
    $num = (int)$match[0];
    $exercise_order[] = $num;
}

// ===== GET EXERCISE STATUS FROM DATABASE =====
// Fetch all exercises for this note
$ex_map = [];
$ex_result = $conn->query("SELECT id, sort_order FROM note_exercises WHERE note_id = $note_id ORDER BY sort_order");
while ($row = $ex_result->fetch_assoc()) {
    $ex_map[$row['sort_order']] = $row['id'];
}

$exercise_attempts = [];
if (!empty($ex_map)) {
    $ids_str = implode(',', array_values($ex_map));
    $attempt_result = $conn->query("SELECT exercise_id, status FROM exercise_attempts WHERE user_id = $uid AND exercise_id IN ($ids_str)");
    while ($row = $attempt_result->fetch_assoc()) {
        $exercise_attempts[$row['exercise_id']] = $row['status'];
    }
}

// ===== FIND THE FIRST INCOMPLETE EXERCISE (by order) =====
$first_incomplete_index = null;
for ($i = 0; $i < count($exercise_order); $i++) {
    $ex_num = $exercise_order[$i];
    $ex_id = isset($ex_map[$ex_num]) ? $ex_map[$ex_num] : null;
    $status = $exercise_attempts[$ex_id] ?? 'not_attempted';
    if ($status != 'marked' && $status != 'paper_pending') {
        $first_incomplete_index = $i;
        break;
    }
}

// ===== SPLIT THE CONTENT INTO SECTIONS =====
$sections = preg_split('/<h[34][^>]*>.*?Exercise\s+(\d+).*?<\/h[34]>/i', $full_content);
$intro_content = $sections[0];
?>
<!DOCTYPE html>
<html><head><title><?=htmlspecialchars($note['title'])?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .student-note-container {
        max-width: 1000px;
        margin: 2rem auto;
        background: var(--card-bg);
        border-radius: 1rem;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
        border-top: 5px solid var(--accent);
        line-height: 1.8;
        font-size: 1.1rem;
        text-align: inherit;
    }
    
    /* ----- NOTE HEADINGS ----- */
    .student-note-container h1 {
        font-size: 2.2rem;
        color: var(--text-color);
        margin: 2rem 0 1rem;
        border-bottom: 2px solid var(--accent);
        padding-bottom: 0.5rem;
    }
    .student-note-container h2 {
        font-size: 1.8rem;
        color: var(--text-color);
        margin: 1.8rem 0 1rem;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }
    .student-note-container h3 {
        font-size: 1.5rem;
        color: var(--text-color);
        margin: 1.5rem 0 0.8rem;
        font-weight: 600;
    }
    .student-note-container h4 {
        font-size: 1.2rem;
        color: var(--text-color);
        margin: 1.2rem 0 0.6rem;
        font-weight: 600;
    }
    
    /* ----- SECTION BLOCKS WITH LOCKING ----- */
    .section-block {
        position: relative;
        margin: 2rem 0;
        padding: 1.5rem;
        border-radius: 1rem;
        transition: all 0.5s ease;
        border: 1px solid var(--border);
    }
    
    .section-block.locked {
        opacity: 0.5;
        pointer-events: none;
        user-select: none;
        position: relative;
    }
    
    .section-block.locked .section-content {
        filter: blur(4px);
        pointer-events: none;
        user-select: none;
    }
    
    .section-block.locked .lock-notification {
        display: block;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.98);
        padding: 2.5rem 3rem;
        border-radius: 1.5rem;
        font-size: 1.4rem;
        font-weight: bold;
        color: #e74c3c;
        border: 3px solid #e74c3c;
        z-index: 9999;
        box-shadow: 0 8px 40px rgba(0,0,0,0.3);
        width: 90%;
        max-width: 650px;
        text-align: center;
        line-height: 1.6;
        pointer-events: auto;
        filter: none !important;
    }
    
    .section-block.unlocked {
        opacity: 1;
        pointer-events: auto;
        user-select: auto;
    }
    .section-block.unlocked .lock-notification {
        display: none;
    }
    .section-block.completed {
        border-left: 5px solid var(--success);
        background: #f0fdf4;
    }
    
    /* ----- FLOATING BUTTONS ----- */
    .floating-actions {
        display: none;
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1000;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 1rem;
        flex-direction: column;
        gap: 0.8rem;
        min-width: 220px;
        transition: all 0.3s ease;
        border: 2px solid var(--accent);
    }
    .floating-actions.visible {
        display: flex;
    }
    .floating-actions .btn {
        width: 100%;
        margin: 0;
        font-size: 0.9rem;
    }
    .floating-actions .btn-paper {
        background: #f39c12;
        color: white;
    }
    .floating-actions .btn-paper:hover {
        background: #e67e22;
    }
    .floating-actions .btn-submit {
        background: var(--success);
        color: white;
    }
    .floating-actions .btn-submit:hover {
        background: #1b8a3a;
    }
    .floating-actions .text-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
    }
    .floating-actions .file-input {
        font-size: 0.8rem;
    }
    .floating-actions .feedback {
        font-size: 0.9rem;
        text-align: center;
    }
    .exercise-indicator {
        font-weight: bold;
        text-align: center;
        color: var(--accent);
        margin-bottom: 0.5rem;
    }
    @media (max-width: 600px) {
        .floating-actions {
            right: 1rem;
            bottom: 1rem;
            min-width: 160px;
            padding: 0.8rem;
        }
        .section-block.locked .lock-notification {
            padding: 1.5rem;
            font-size: 1.1rem;
            max-width: 90%;
        }
    }
    
</style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; flex-wrap:wrap;">
        <h2><?=htmlspecialchars($note['title'])?></h2>
        <a href="library.php" class="btn-back">← Back</a>
    </div>
    <div class="student-note-container" id="main-container">
        <?php
        // INTRODUCTION (always unlocked)
        ?>
        <div class="section-block unlocked">
            <div class="section-content">
                <?php echo $intro_content; ?>
            </div>
        </div>
        <?php
        
        // ===== RENDER EXERCISE SECTIONS WITH LOCKING =====
        $passed_first_incomplete = false;
        
        for ($i = 1; $i < count($sections); $i++) {
            $content_part = $sections[$i];
            $heading_text = isset($exercise_positions[$i-1][0]) ? $exercise_positions[$i-1][0] : '';
            
            // Get the exercise number and ID
            $ex_num = isset($exercise_order[$i-1]) ? $exercise_order[$i-1] : $i;
            $ex_id = isset($ex_map[$ex_num]) ? $ex_map[$ex_num] : null;
            $status = $exercise_attempts[$ex_id] ?? 'not_attempted';
            $is_completed = ($status == 'marked' || $status == 'paper_pending');
            
            // LOCKING LOGIC:
            // - All sections before the first incomplete exercise are UNLOCKED.
            // - The first incomplete exercise itself is UNLOCKED.
            // - Everything after the first incomplete exercise is LOCKED.
            $is_locked = false;
            if ($first_incomplete_index !== null) {
                if ($i-1 < $first_incomplete_index) {
                    $is_locked = false;
                } elseif ($i-1 == $first_incomplete_index) {
                    $is_locked = false;
                } else {
                    $is_locked = true;
                }
            } else {
                // If all exercises are completed, unlock everything
                $is_locked = false;
            }
            
            // SPECIAL CASE: If this is the first exercise and it's completed, the next one should be locked
            // But if all are completed, everything is unlocked (handled above)
            ?>
            <div class="section-block <?php echo $is_locked ? 'locked' : 'unlocked'; ?> <?php echo $is_completed ? 'completed' : ''; ?>"
                 data-exercise-id="<?php echo $ex_id ?? ''; ?>"
                 data-exercise-number="<?php echo $ex_num; ?>">
                
                <div class="lock-notification">
                    🔒 This section is locked.<br>Complete the previous exercise.
                </div>
                
                <div class="section-content">
                    <?php echo $heading_text; ?>
                    <?php echo $content_part; ?>
                </div>
                
                <?php if (!$is_locked && !$is_completed && $ex_id): ?>
                    <div class="exercise-form-wrapper" style="display:none;">
                        <input type="hidden" name="exercise_id" value="<?php echo $ex_id; ?>">
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>
    </div>
</div>

<!-- FLOATING ACTION BUTTONS -->
<div id="floatingActions" class="floating-actions">
    <div class="exercise-indicator" id="exerciseIndicator">📝 Exercise</div>
    <form id="digitalForm" method="post" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:0.5rem;">
        <input type="hidden" name="exercise_id" id="activeExerciseId" value="">
        <textarea name="answer_text" class="text-input" rows="2" placeholder="Type your answer here..."></textarea>
        <input type="file" name="answer_file" class="file-input" accept=".jpg,.png,.pdf,.txt">
        <button type="submit" name="submit_digital" class="btn btn-submit">💻 Submit Digital</button>
    </form>
    <form id="paperForm" method="post" style="display:flex; flex-direction:column; gap:0.5rem;">
        <input type="hidden" name="exercise_id" id="activeExerciseIdPaper" value="">
        <button type="submit" name="submit_paper" class="btn btn-paper">📄 I will submit on paper</button>
    </form>
    <div id="floatingFeedback" class="feedback"></div>
</div>

<?php include_once 'includes/footer.php'; ?>
<script>
const currentNoteId = <?php echo $note_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    const floatingActions = document.getElementById('floatingActions');
    const exerciseIndicator = document.getElementById('exerciseIndicator');
    const activeExerciseIdInput = document.getElementById('activeExerciseId');
    const activeExerciseIdPaperInput = document.getElementById('activeExerciseIdPaper');
    const digitalForm = document.getElementById('digitalForm');
    const paperForm = document.getElementById('paperForm');
    const floatingFeedback = document.getElementById('floatingFeedback');

    // Find all exercise blocks
    const exerciseBlocks = [];
    const blocks = document.querySelectorAll('.section-block');
    blocks.forEach(block => {
        const exerciseId = block.dataset.exerciseId;
        const exerciseNumber = block.dataset.exerciseNumber;
        if (exerciseId) {
            const isCompleted = block.classList.contains('completed');
            const isLocked = block.classList.contains('locked');
            exerciseBlocks.push({
                id: parseInt(exerciseId),
                number: parseInt(exerciseNumber),
                block: block,
                completed: isCompleted,
                locked: isLocked
            });
        }
    });
    exerciseBlocks.sort((a, b) => a.number - b.number);

    // --- FIXED OBSERVER: Handles both SHOW and HIDE ---
    const observer = new IntersectionObserver((entries) => {
        let targetExercise = null;
        
        entries.forEach(entry => {
            const block = entry.target;
            const exerciseId = block.dataset.exerciseId;
            const exerciseNumber = block.dataset.exerciseNumber;
            
            if (!exerciseId || !exerciseNumber) return;
            
            const isCompleted = block.classList.contains('completed');
            const isLocked = block.classList.contains('locked');
            
            // If an unlocked, uncompleted exercise enters view, target it
            if (!isCompleted && !isLocked && entry.isIntersecting) {
                targetExercise = {
                    id: parseInt(exerciseId),
                    number: parseInt(exerciseNumber),
                    block: block
                };
            }
        });

        if (targetExercise) {
            // Show modal for the exercise in view
            floatingActions.classList.add('visible');
            activeExerciseIdInput.value = targetExercise.id;
            activeExerciseIdPaperInput.value = targetExercise.id;
            exerciseIndicator.textContent = `📝 Exercise ${targetExercise.number}`;
            floatingFeedback.innerHTML = '';
        } else {
            // Hide modal when no unlocked exercise is in view
            floatingActions.classList.remove('visible');
        }
    }, { threshold: 0.3 }); // Removed the broken rootMargin

    // Observe all exercise blocks
    exerciseBlocks.forEach(ex => {
        observer.observe(ex.block);
    });

    // --- DIGITAL SUBMIT ---
    digitalForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const exId = parseInt(activeExerciseIdInput.value);
        const formData = new FormData(this);
        const text = formData.get('answer_text')?.trim() || '';
        const file = formData.get('answer_file');

        if (!text && (!file || file.size === 0)) {
            floatingFeedback.innerHTML = '❌ Please provide an answer (text or file).';
            floatingFeedback.style.color = '#ef4444';
            return;
        }

        floatingFeedback.innerHTML = '⏳ Submitting...';
        floatingFeedback.style.color = '#f59e0b';

        fetch('student_view_note.php?id=' + currentNoteId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('Digital answer submitted!') || data.includes('success')) {
                floatingFeedback.innerHTML = '✅ Submitted!';
                floatingFeedback.style.color = '#22c55e';
                
                blocks.forEach(block => {
                    if (block.dataset.exerciseId == exId) {
                        block.classList.add('completed');
                        block.classList.remove('locked');
                        block.classList.add('unlocked');
                        observer.unobserve(block);
                        observer.observe(block);
                    }
                });
                
                setTimeout(() => {
                    floatingActions.classList.remove('visible');
                    if (window.MathJax) MathJax.typesetPromise();
                }, 1500);
            } else {
                floatingFeedback.innerHTML = '❌ Submission failed. Please try again.';
                floatingFeedback.style.color = '#ef4444';
            }
        })
        .catch(error => {
            console.error(error);
            floatingFeedback.innerHTML = '❌ Network error.';
            floatingFeedback.style.color = '#ef4444';
        });
    });

    // --- PAPER SUBMIT ---
    paperForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const exId = parseInt(activeExerciseIdPaperInput.value);
        const formData = new FormData(this);

        floatingFeedback.innerHTML = '⏳ Recording promise...';
        floatingFeedback.style.color = '#f59e0b';

        fetch('student_view_note.php?id=' + currentNoteId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('promised to submit') || data.includes('success')) {
                floatingFeedback.innerHTML = '✅ Promise recorded!';
                floatingFeedback.style.color = '#22c55e';
                
                blocks.forEach(block => {
                    if (block.dataset.exerciseId == exId) {
                        block.classList.add('completed');
                        block.classList.remove('locked');
                        block.classList.add('unlocked');
                        observer.unobserve(block);
                        observer.observe(block);
                    }
                });
                
                setTimeout(() => {
                    floatingActions.classList.remove('visible');
                    if (window.MathJax) MathJax.typesetPromise();
                }, 1500);
            } else {
                floatingFeedback.innerHTML = '❌ Promise failed. Please try again.';
                floatingFeedback.style.color = '#ef4444';
            }
        })
        .catch(error => {
            console.error(error);
            floatingFeedback.innerHTML = '❌ Network error.';
            floatingFeedback.style.color = '#ef4444';
        });
    });
});
</script>
</body></html>