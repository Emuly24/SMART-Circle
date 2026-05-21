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

// Fetch all sections for this note
$sections = $conn->query("SELECT s.*, e.status as attempt_status, e.answer_text 
    FROM note_sections s
    LEFT JOIN note_exercises ex ON s.exercise_id = ex.id
    LEFT JOIN exercise_attempts e ON ex.id = e.exercise_id AND e.user_id = $uid
    WHERE s.note_id = $note_id
    ORDER BY s.sort_order");

// Helper: Clean literal \r\n – NO stripslashes()
function clean_content($raw) {
    return str_replace(['\\r\\n', '\\r', '\\n'], ["\r\n", "\r", "\n"], $raw);
}

// Helper: Fix common LaTeX rendering issues on the fly
function fix_latex_rendering($content) {
    // 1. Replace raw (aeqO) with proper LaTeX
    $content = str_replace('(aeqO)', '\\quad (a \\neq 0)', $content);
    // 2. Remove newlines inside \left( ... \right) pairs
    $content = preg_replace('/\\\\left\\s*\\(([^\\"]*?)\\s*\\\\)\\s*\\)/', '\\left($1\\right)', $content);
    return $content;
}

// Collect all sections
$sectionData = array();
while($sec = $sections->fetch_assoc()) {
    $sectionData[] = $sec;
}

// FALLBACK: If note_sections is empty or has empty content, use the raw note content
$useRawContent = false;
if (empty($sectionData)) {
    $useRawContent = true;
} else {
    $hasRealContent = false;
    foreach ($sectionData as $sec) {
        if (!empty(trim($sec['content']))) {
            $hasRealContent = true;
            break;
        }
    }
    if (!$hasRealContent) {
        $useRawContent = true;
    }
}

// If no valid sections, fallback to raw note
if ($useRawContent) {
    $sectionData = [];
    $sectionData[] = [
        'id' => 0,
        'note_id' => $note_id,
        'sort_order' => 1,
        'section_type' => 'introduction',
        'content' => $note['content'],
        'exercise_id' => null,
        'attempt_status' => null,
        'answer_text' => null
    ];
}

// ----- LOGIC: Lock everything AFTER the first incomplete exercise -----
$firstIncompleteExerciseId = null;
foreach ($sectionData as $sec) {
    if ($sec['section_type'] == 'exercise' && $sec['exercise_id']) {
        $status = $sec['attempt_status'] ?? 'not_attempted';
        $isCompleted = ($status == 'marked' || $status == 'paper_pending');
        if (!$isCompleted) {
            $firstIncompleteExerciseId = $sec['exercise_id'];
            break;
        }
    }
}

$isLocked = false;
$exerciseCount = 0;
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
    
    .section-block {
        position: relative;
        margin: 2rem 0;
        padding: 1.5rem;
        border-radius: 1rem;
        transition: all 0.5s ease;
        border: 1px solid var(--border);
    }
    
    /* ----- LOCKED STATE ----- */
    .section-block.locked {
        opacity: 0.5;
        pointer-events: none;
        user-select: none;
        position: relative;
    }
    
    .section-block.locked .section-content {
        filter: blur(2px);
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
        $isLocked = false;
        $exerciseCount = 0;
        
        foreach ($sectionData as $sec) {
            $isExercise = ($sec['section_type'] == 'exercise');
            $isCompleted = false;
            $exerciseNumber = '';
            
            // Check if this is the first incomplete exercise
            $isFirstIncompleteExercise = ($isExercise && $sec['exercise_id'] == $firstIncompleteExerciseId);
            
            // LOCK LOGIC
            if ($isFirstIncompleteExercise) {
                $isLocked = false;
            } elseif ($isExercise && $sec['exercise_id'] != $firstIncompleteExerciseId) {
                $isLocked = true;
            } elseif (!$isExercise && $firstIncompleteExerciseId !== null) {
                $isLocked = true;
            }
            
            // Track exercise status
            if ($isExercise && $sec['exercise_id']) {
                $exerciseCount++;
                $exerciseNumber = $exerciseCount;
                $status = $sec['attempt_status'] ?? 'not_attempted';
                $isCompleted = ($status == 'marked' || $status == 'paper_pending');
                
                if ($sec['exercise_id'] == $firstIncompleteExerciseId) {
                    $isLocked = false;
                }
            }
            
            // Clean and fix LaTeX
            $cleanContent = fix_latex_rendering(clean_content($sec['content']));
            
            // If content is empty and we are in fallback mode, it should not be empty
            if (empty(trim($cleanContent))) {
                $cleanContent = "<p><em>Content is being processed...</em></p>";
            }
            ?>
            <div class="section-block <?php echo $isLocked ? 'locked' : 'unlocked'; ?> <?php echo $isCompleted ? 'completed' : ''; ?>" 
                 data-section-type="<?php echo $sec['section_type']; ?>"
                 data-exercise-id="<?php echo $sec['exercise_id'] ?? ''; ?>"
                 data-exercise-number="<?php echo $exerciseNumber; ?>">
                
                <div class="lock-notification">
                    🔒 This section is locked.<br>Complete the previous exercise.
                </div>
                
                <div class="section-content">
                    <?php echo $cleanContent; ?>
                    <?php if ($isExercise && $sec['exercise_id']): ?>
                        <div class="exercise-form-wrapper" style="display:none;">
                            <input type="hidden" name="exercise_id" value="<?php echo $sec['exercise_id']; ?>">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            // After rendering the first incomplete exercise, lock everything else
            if ($sec['exercise_id'] == $firstIncompleteExerciseId) {
                $isLocked = true;
            }
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

        const exerciseBlocks = [];
        const blocks = document.querySelectorAll('.section-block');
        blocks.forEach(block => {
            const sectionType = block.dataset.sectionType;
            const exerciseId = block.dataset.exerciseId;
            const exerciseNumber = block.dataset.exerciseNumber;
            
            if (sectionType === 'exercise' && exerciseId) {
                const isCompleted = block.classList.contains('completed');
                exerciseBlocks.push({
                    id: parseInt(exerciseId),
                    number: exerciseNumber ? parseInt(exerciseNumber) : 0,
                    block: block,
                    completed: isCompleted
                });
            }
        });

        exerciseBlocks.sort((a, b) => a.number - b.number);

        const observer = new IntersectionObserver((entries) => {
            let targetExercise = null;
            
            entries.forEach(entry => {
                const block = entry.target;
                const exerciseId = block.dataset.exerciseId;
                const exerciseNumber = block.dataset.exerciseNumber;
                
                if (!exerciseId || !exerciseNumber) return;
                
                const isCompleted = block.classList.contains('completed');
                const isLocked = block.classList.contains('locked');
                
                if (!isCompleted && !isLocked && entry.isIntersecting) {
                    targetExercise = {
                        id: parseInt(exerciseId),
                        number: parseInt(exerciseNumber),
                        block: block
                    };
                }
            });

            if (targetExercise) {
                floatingActions.classList.add('visible');
                activeExerciseIdInput.value = targetExercise.id;
                activeExerciseIdPaperInput.value = targetExercise.id;
                exerciseIndicator.textContent = `📝 Exercise ${targetExercise.number}`;
                floatingFeedback.innerHTML = '';
            } else {
                floatingActions.classList.remove('visible');
            }
        }, { threshold: 0.3 });

        exerciseBlocks.forEach(ex => {
            observer.observe(ex.block);
        });

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
                        const visible = document.querySelector('.section-block[data-exercise-id="' + exId + '"]');
                        if (visible && visible.classList.contains('completed')) {
                            floatingActions.classList.remove('visible');
                        }
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
                        const visible = document.querySelector('.section-block[data-exercise-id="' + exId + '"]');
                        if (visible && visible.classList.contains('completed')) {
                            floatingActions.classList.remove('visible');
                        }
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