<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

// --------------------- EXERCISE HANDLING (SAME AS BEFORE) ---------------------
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

// ===== DEBUG: Force display of raw note content =====
// We will bypass the section logic entirely to see the full note
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
        echo "<!-- DEBUG: RAW NOTE CONTENT FROM DATABASE -->\n";
        echo $note['content'];
        ?>
    </div>
</div>
<?php include_once 'includes/footer.php'; ?>
</body></html>