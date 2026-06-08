<?php
// ===== SESSION SETUP =====
$session_path = __DIR__ . '/sessions';
if (!is_dir($session_path)) {
    mkdir($session_path, 0755, true);
}
session_save_path($session_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    session_write_close();
    header("Location: login.php");
    exit;
}

require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];

// Fetch note content – NO extra processing, NO exercise extraction
$note_stmt = $conn->prepare("SELECT * FROM notes WHERE id = ?");
$note_stmt->bind_param("i", $note_id);
$note_stmt->execute();
$note = $note_stmt->get_result()->fetch_assoc();

if (!$note) die("Note not found");

// Log view activity
if (function_exists('log_activity')) {
    log_activity($uid, "view_note", "Note ID: $note_id");
}
?>
<!DOCTYPE html>
<html><head><title><?= htmlspecialchars($note['title']) ?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<style>
    /* ===== BASE ===== */
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    
    /* ===== MAIN CONTAINER – No override of editor layout ===== */
    .student-note-container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 2.5rem;
        background: var(--card-bg);
        box-shadow: var(--card-shadow);
        border-radius: 1rem;
        border-top: 5px solid var(--accent);
    }
    
    /* ===== BEAUTIFUL BRAND‑COLOUR TEXT (bolder, rich slate) ===== */
    .student-note-container {
        color: #1e293b;          /* Dark slate – rich, professional */
        font-weight: 500;        /* Bolder than normal */
    }
    
    /* Allow editor headings to keep their natural weight and color */
    .student-note-container h1,
    .student-note-container h2,
    .student-note-container h3,
    .student-note-container h4,
    .student-note-container h5,
    .student-note-container h6 {
        all: revert;
        color: #0f172a;         /* Even darker for headings */
        font-weight: 700;
    }
    
    /* Allow editor bold / strong tags to be bolder */
    .student-note-container strong,
    .student-note-container b {
        font-weight: 700;
        color: #0f172a;
    }
    
    /* ===== LATEX EQUATIONS – #C7390D, bold, same font as text ===== */
    /* Target MathJax output – works for both inline and block equations */
    .MathJax,
    .MathJax *,
    .mjx-chtml,
    .mjx-math,
    .mjx-box,
    .mjx-mtext,
    .mjx-mi,
    .mjx-mn,
    .mjx-mo,
    .mjx-mrow,
    .mjx-table,
    .mjx-mtd,
    .mjx-mtr {
        color: #C7390D !important;
        font-weight: bold !important;
        font-family: inherit !important;
    }
    
    /* Ensure block equations (display math) also get the color */
    .MathJax_Display {
        color: #C7390D !important;
    }
    
    /* ===== ALLOW EDITOR STYLES TO PASS THROUGH ===== */
    .student-note-container p,
    .student-note-container div,
    .student-note-container span,
    .student-note-container ol,
    .student-note-container ul,
    .student-note-container li,
    .student-note-container table {
        all: revert;
        /* The container's color and weight will apply if not overridden */
    }
    
    .student-note-container img {
        max-width: 100%;
        height: auto;
    }
    
    .student-note-container figure {
        margin: 0;
    }
    
    /* ===== ASSIGNMENT PROMPT – standalone, beautiful ===== */
    .assignment-prompt {
        text-align: center;
        margin-top: 3rem;
        padding: 2.5rem;
        background: #f8fafc;
        border-radius: 1.5rem;
        border: 2px dashed var(--accent);
    }
    
    .assignment-prompt p {
        font-size: 1.15rem;
        color: #1e293b;
        font-weight: 500;
        margin-bottom: 1.5rem;
    }
    
    .assignment-prompt .btn {
        background: var(--accent);
        color: #1e293b;
        padding: 0.85rem 3rem;
        border-radius: 2.5rem;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.1rem;
        display: inline-block;
        transition: 0.25s ease;
        box-shadow: 0 4px 12px rgba(212,175,55,0.3);
    }
    
    .assignment-prompt .btn:hover {
        transform: scale(1.04) translateY(-2px);
        background: var(--accent-dark);
        box-shadow: 0 8px 24px rgba(212,175,55,0.4);
    }
</style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">
    <div style="margin-bottom:1rem; display:flex; justify-content:space-between; flex-wrap:wrap;">
        <h2><?= htmlspecialchars($note['title']) ?></h2>
        <a href="library.php" class="btn-back">← Back</a>
    </div>
    
    <div class="student-note-container" id="main-container">
        <!-- ===== THE NOTE CONTENT – EXACTLY AS STORED ===== -->
        <?php echo $note['content']; ?>
        
        <!-- ===== ASSIGNMENT PROMPT ===== -->
        <div class="assignment-prompt">
            <p>
                ✅ You have reached the end of this note.<br>
                All exercises from this note have been moved to the <strong>Assignment Room</strong>.
            </p>
            <a href="assignments.php" class="btn">📝 Open Assignment Room</a>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
</body>
</html>