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

// ===== FETCH THE NOTE CONTENT =====
$full_content = $note['content'];

// ===== REMOVE ALL EXERCISE BLOCKS FROM THE CONTENT =====
// This regex finds <h3> or <h4> containing "Exercise X" and removes the entire exercise block.
$exercise_pattern = '/<h[34][^>]*>.*?Exercise\s+(\d+).*?<\/h[34]>(.*?)(?=<h[34]|$)/si';
$clean_content = preg_replace($exercise_pattern, '', $full_content);

// If the note has no exercises or the pattern didn't match, display the full content
if (empty(trim($clean_content))) {
    $clean_content = $full_content;
}
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
        padding: 2.5rem;
        background: var(--card-bg);
        box-shadow: var(--card-shadow);
        border-radius: 1rem;
        border-top: 5px solid var(--accent);
        /* No extra alignment overrides - respects your editor's alignment */
    }
    .assignment-prompt {
        text-align: center;
        margin-top: 3rem;
        padding: 2rem;
        background: #f8fafc;
        border-radius: 1rem;
        border: 2px dashed var(--accent);
    }
    .assignment-prompt .btn {
        background: var(--accent);
        color: #1e293b;
        padding: 0.75rem 2.5rem;
        border-radius: 2rem;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        transition: 0.2s;
    }
    .assignment-prompt .btn:hover {
        transform: scale(1.05);
        background: var(--accent-dark);
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
        <?php echo $clean_content; ?>
        
        <!-- ===== ASSIGNMENT ROOM PROMPT ===== -->
        <div class="assignment-prompt">
            <p style="margin-bottom: 1rem; font-size: 1.1rem;">
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