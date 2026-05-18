<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$note_id = (int)$_GET['id'];
$note = $conn->query("SELECT * FROM notes WHERE id=$note_id")->fetch_assoc();
if (!$note) die("Note not found");

// Content lock check
if (!is_content_unlocked('note', $note_id, $uid)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Content Locked</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card error">
            <h2>🔒 Content Locked</h2>
            <p>This note is not yet available for your group. Please wait until the admin unlocks it after your group meeting.</p>
            <div class="card-buttons">
                <a href="library.php" class="btn-back">← Back to Library</a>
            </div>
        </div>
    </div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
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

// Fetch exercises with current status and attempts (if any are defined in the DB)
$exercises = $conn->query("SELECT e.*, a.answer_text, a.answer_file_path, a.marks_awarded, a.feedback, a.status, a.promised_at 
    FROM note_exercises e 
    LEFT JOIN exercise_attempts a ON e.id = a.exercise_id AND a.user_id = $uid
    WHERE e.note_id = $note_id ORDER BY e.sort_order");

$all_attempted = true;
while ($ex = $exercises->fetch_assoc()) {
    if ($ex['status'] !== 'marked' && empty($ex['answer_text']) && empty($ex['answer_file_path']) && $ex['status'] !== 'paper_pending') {
        $all_attempted = false;
        break;
    }
}
$exercises->data_seek(0);

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'paper_promised') $msg = "Thank you. Your promise to submit on paper has been recorded.";
?>
<!DOCTYPE html>
<html><head><title><?=htmlspecialchars($note['title'])?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<style>
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
    }
    .student-note-container h1, .student-note-container h2, .student-note-container h3 {
        color: var(--accent);
    }
    .student-note-container img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
    }
    .student-note-container pre {
        background: var(--card-alt-bg);
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
    }
    .student-note-container blockquote {
        border-left: 4px solid var(--accent);
        padding-left: 1rem;
        margin: 1rem 0;
        color: var(--text-muted);
    }
    .student-note-container table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }
    .student-note-container table th, .student-note-container table td {
        border: 1px solid var(--card-alt-bg);
        padding: 0.5rem;
    }
    .student-note-container table th {
        background: var(--accent);
        color: #1e293b;
    }
    .student-note-container .mermaid {
        background: var(--card-alt-bg);
        padding: 1rem;
        border-radius: 0.5rem;
        margin: 1rem 0;
    }

    /* Custom CSS for the locked sections */
    .locked-section-wrapper {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.8s ease, opacity 0.8s ease, margin 0.8s ease;
        margin: 0;
    }
    .locked-section-wrapper.unlocked {
        max-height: 3000px;
        opacity: 1;
        margin: 2rem 0;
    }
    .locked-placeholder {
        background: #f0f4f8;
        padding: 1rem;
        border-radius: 1rem;
        text-align: center;
        color: #64748b;
        font-size: 0.9rem;
        box-shadow: inset 0 0 0 1px var(--border);
        margin: 1.5rem 0;
    }
    .locked-placeholder strong {
        color: var(--error);
    }
    .locked-placeholder .lock-icon {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 0.5rem;
    }
    .locked-section-wrapper .real-content {
        display: none;
    }
    .locked-section-wrapper.unlocked .real-content {
        display: block;
    }
    .locked-section-wrapper.unlocked .locked-placeholder {
        display: none;
    }

    .submit-status {
        font-size: 0.9rem;
        margin-top: 0.5rem;
        color: var(--success);
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
    <div class="student-note-container">

        <!-- ============ INTERACTIVE NOTE CONTENT ============ -->
        <div id="main-container">

            <!-- ============ UNIT HEADER ============ -->
            <h1><span>Exponential and Logarithmic Functions</span></h1>
            <p><em>Adapted from Target Book 3 (Unit 5) &amp; Arise Mathematics (Unit 10)</em></p>

            <!-- ============ INTRODUCTION ============ -->
            <div id="section-intro">
                <h2>📖 Introduction</h2>
                <p>Exponential and logarithmic functions are among the most powerful tools in mathematics. They are used to model a vast range of real-world phenomena, from <strong>population growth</strong> and <strong>radioactive decay</strong> to <strong>interest rates</strong>, <strong>sound intensity</strong>, and even <strong>earthquake magnitudes</strong>.</p>
                
                <div class="note-box">
                    <h3>📌 Success Criteria</h3>
                    <p>By the end of this unit, you should be able to:</p>
                    <ul>
                        <li>Understand the <span class="highlight">history</span> and real-life applications of exponential and logarithmic functions.</li>
                        <li><span class="highlight">Define</span> indices, exponential functions, and logarithms.</li>
                        <li><span class="highlight">Express</span> numbers as powers of a given base.</li>
                        <li>Apply the <span class="highlight">rules of indices</span> (including fractional and negative indices).</li>
                        <li><span class="highlight">Solve</span> exponential equations using the method of same bases.</li>
                        <li>Understand and use the <span class="highlight">laws of logarithms</span>.</li>
                        <li><span class="highlight">Solve</span> logarithmic equations.</li>
                    </ul>
                </div>

                <div class="definition-box">
                    <h3>🔍 A Brief History</h3>
                    <p>The concept of <strong>logarithms</strong> was introduced by <strong>John Napier</strong> in 1614. His work revolutionized computation by transforming complex multiplication and division into simpler addition and subtraction. Later, <strong>Leonhard Euler</strong> formalized exponential functions and established their inverse relationship with logarithms, laying the foundation for modern applied mathematics.</p>
                </div>
            </div>

            <!-- ============ SECTION 1: INDICES ============ -->
            <div id="section-indices">
                <h2>📐 Indices (Laws of Exponents)</h2>

                <p>An <span class="highlight">index</span> (plural: indices) is a small number placed to the right and above a base number to indicate repeated multiplication.</p>

                <div class="formula-box">
                    <p>If <em>\(a\)</em> is a real number and <em>\(n\)</em> is a positive integer:</p>
                    <strong>\[
                    a^n = a \times a \times a \times \cdots \times a \; (n \text{ times})
                    \]</strong>
                </div>

                <h3>The Five Basic Laws of Indices</h3>

                <!-- RULE 1 -->
                <div class="definition-box">
                    <h4>📌 Rule 1: Multiplication</h4>
                    <p><strong>Rule:</strong> When multiplying two numbers with the same base, add the powers.</p>
                    <div class="formula-box">
                        \[
                        a^{m} \times a^{n} = a^{m+n}
                        \]
                    </div>
                    <h4>Example 1 (With Steps):</h4>
                    <p>Simplify \( 2^3 \times 2^2 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> The base is the same (\(2\)).</li>
                        <li><strong>Apply the rule:</strong> Add the powers: \(3 + 2 = 5\)</li>
                        <li><strong>Write the result:</strong> \(2^5\)</li>
                        <li><strong>Calculate:</strong> \(2^5 = 32\)</li>
                    </ol>
                    <h4>Example 2 (With Steps):</h4>
                    <p>Simplify \( 5^2 \times 5^4 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> Base is \(5\).</li>
                        <li><strong>Apply the rule:</strong> \(2 + 4 = 6\)</li>
                        <li><strong>Write the result:</strong> \(5^6\)</li>
                    </ol>
                    <h4>✅ Check Your Understanding:</h4>
                    <ol>
                        <li>Simplify \( 3^4 \times 3^2 \)</li>
                        <li>Simplify \( 10^1 \times 10^3 \)</li>
                    </ol>
                    <p><em>Check your answers: 1) \(3^6\), 2) \(10^4\).</em></p>
                </div>

                <!-- RULE 2 -->
                <div class="definition-box">
                    <h4>📌 Rule 2: Division</h4>
                    <p><strong>Rule:</strong> When dividing two numbers with the same base, subtract the powers.</p>
                    <div class="formula-box">
                        \[
                        a^{m} \div a^{n} = a^{m-n} \quad (a \neq 0)
                        \]
                    </div>
                    <h4>Example 1 (With Steps):</h4>
                    <p>Simplify \( 3^5 \div 3^2 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> Base is \(3\).</li>
                        <li><strong>Apply the rule:</strong> Subtract the powers: \(5 - 2 = 3\)</li>
                        <li><strong>Write the result:</strong> \(3^3\)</li>
                    </ol>
                    <h4>Example 2 (With Steps):</h4>
                    <p>Simplify \( 10^6 \div 10^2 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> Base is \(10\).</li>
                        <li><strong>Apply the rule:</strong> \(6 - 2 = 4\)</li>
                        <li><strong>Write the result:</strong> \(10^4\)</li>
                    </ol>
                    <h4>✅ Check Your Understanding:</h4>
                    <ol>
                        <li>Simplify \( 8^5 \div 8^3 \)</li>
                        <li>Simplify \( 7^9 \div 7^4 \)</li>
                    </ol>
                    <p><em>Check your answers: 1) \(8^2\), 2) \(7^5\).</em></p>
                </div>

                <!-- RULE 3 -->
                <div class="definition-box">
                    <h4>📌 Rule 3: Power of a Power</h4>
                    <p><strong>Rule:</strong> When a power is raised to another power, multiply the exponents.</p>
                    <div class="formula-box">
                        \[
                        (a^{m})^{n} = a^{mn}
                        \]
                    </div>
                    <h4>Example 1 (With Steps):</h4>
                    <p>Simplify \( (5^2)^3 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> We have a power inside a power.</li>
                        <li><strong>Apply the rule:</strong> Multiply the exponents: \(2 \times 3 = 6\)</li>
                        <li><strong>Write the result:</strong> \(5^6\)</li>
                    </ol>
                    <h4>Example 2 (With Steps):</h4>
                    <p>Simplify \( (8^4)^2 \)</p>
                    <ol>
                        <li><strong>Identify:</strong> Power inside a power.</li>
                        <li><strong>Apply the rule:</strong> \(4 \times 2 = 8\)</li>
                        <li><strong>Write the result:</strong> \(8^8\)</li>
                    </ol>
                    <h4>✅ Check Your Understanding:</h4>
                    <ol>
                        <li>Simplify \( (3^2)^4 \)</li>
                        <li>Simplify \( (x^5)^3 \)</li>
                    </ol>
                    <p><em>Check your answers: 1) \(3^8\), 2) \(x^{15}\).</em></p>
                </div>

                <!-- RULE 4 -->
                <div class="definition-box">
                    <h4>📌 Rule 4: Zero Power</h4>
                    <p><strong>Rule:</strong> Any non-zero number raised to the power of zero is equal to 1.</p>
                    <div class="formula-box">
                        \[
                        a^{0} = 1 \quad (a \neq 0)
                        \]
                    </div>
                    <h4>Example:</h4>
                    <p>\( 7^0 = 1 \), \( x^0 = 1 \), \( 100^0 = 1 \)</p>
                </div>

                <!-- RULE 5 -->
                <div class="definition-box">
                    <h4>📌 Rule 5: Negative Power</h4>
                    <p><strong>Rule:</strong> Any non-zero number raised to a negative power is the reciprocal of the same number raised to the positive power.</p>
                    <div class="formula-box">
                        \[
                        a^{-m} = \frac{1}{a^{m}} \quad (a \neq 0)
                        \]
                    </div>
                    <h4>Example 1 (With Steps):</h4>
                    <p>Simplify \( 2^{-3} \)</p>
                    <ol>
                        <li><strong>Apply the rule:</strong> \( \frac{1}{2^3} \)</li>
                        <li><strong>Calculate:</strong> \( \frac{1}{8} \)</li>
                    </ol>
                    <h4>Example 2 (With Steps):</h4>
                    <p>Simplify \( 10^{-2} \)</p>
                    <ol>
                        <li><strong>Apply the rule:</strong> \( \frac{1}{10^2} \)</li>
                        <li><strong>Calculate:</strong> \( \frac{1}{100} \)</li>
                    </ol>
                    <h4>✅ Check Your Understanding:</h4>
                    <ol>
                        <li>Write \( 3^{-4} \) with a positive index.</li>
                        <li>Write \( 5^{-1} \) with a positive index.</li>
                    </ol>
                    <p><em>Check your answers: 1) \( \frac{1}{3^4} \), 2) \( \frac{1}{5} \).</em></p>
                </div>

                <!-- EXERCISE 1 -->
                <div class="exercise-block" id="indices-exercise">
                    <h3>✏️ Exercise 1: Applying the Laws of Indices</h3>
                    <p>Evaluate the following, leaving your answer in index form:</p>
                    <ol>
                        <li>\( 4^8 \times 4^2 \)</li>
                        <li>\( 10^5 \div 10^2 \)</li>
                        <li>\( (8^3)^4 \)</li>
                        <li>\( 2^{-3} \)</li>
                        <li>\( 5^0 \times 3^2 \)</li>
                        <li>\( (6^2)^{-1} \)</li>
                    </ol>
                    <form id="form-indices" onsubmit="submitExercise(event, 'indices', 'section-fractional')">
                        <textarea id="indices-answer" name="answer_text" rows="4" placeholder="Write your full working here..."></textarea>
                        <input type="hidden" name="exercise_id" value="101">
                        <button type="submit" name="submit_digital" class="btn">Submit &amp; Unlock Next Section</button>
                    </form>
                    <div id="indices-feedback" class="submit-status"></div>
                </div>
            </div>

            <!-- ============ SECTION 2: FRACTIONAL INDICES (LOCKED) ============ -->
            <div id="section-fractional-wrapper" class="locked-section-wrapper">
                <div class="locked-placeholder">
                    <span class="lock-icon">🔒</span>
                    <strong>Section Locked</strong><br>
                    Complete <strong>Exercise 1</strong> above to unlock the next part on Fractional Indices.
                </div>
                <div class="real-content">
                    <h2>🔢 Fractional Indices</h2>
                    <p>Fractional indices indicate <span class="highlight">roots</span>. The denominator of the fraction tells us the root, and the numerator tells us the power.</p>
                    <div class="formula-box">
                        \[
                        a^{\frac{m}{n}} = \left(\sqrt[n]{a}\right)^{m} \quad \text{or} \quad a^{\frac{m}{n}} = \sqrt[n]{a^m}
                        \]
                    </div>
                    <div class="note-box">
                        <p><strong>Important Special Cases:</strong></p>
                        <ul>
                            <li>\( a^{\frac{1}{2}} = \sqrt{a} \) (square root)</li>
                            <li>\( a^{\frac{1}{3}} = \sqrt[3]{a} \) (cube root)</li>
                            <li>\( a^{\frac{1}{n}} = \sqrt[n]{a} \) (n-th root)</li>
                        </ul>
                    </div>
                    <h3>Examples:</h3>
                    <ol>
                        <li><strong>\( 8^{\frac{1}{3}} \)</strong>: \( \sqrt[3]{8} = 2 \)</li>
                        <li><strong>\( 16^{\frac{3}{4}} \)</strong>: \( (\sqrt[4]{16})^{3} = 2^{3} = 8 \)</li>
                        <li><strong>\( 27^{-\frac{2}{3}} \)</strong>: \( \frac{1}{(\sqrt[3]{27})^{2}} = \frac{1}{3^{2}} = \frac{1}{9} \)</li>
                    </ol>

                    <div class="exercise-block" id="fractional-exercise">
                        <h3>✏️ Exercise 2: Fractional Indices</h3>
                        <p>Write down the values of the following:</p>
                        <ol>
                            <li>\( 100^{-\frac{1}{2}} \)</li>
                            <li>\( 16^{\frac{3}{2}} \)</li>
                            <li>\( 64^{-\frac{2}{3}} \)</li>
                        </ol>
                        <form id="form-fractional" onsubmit="submitExercise(event, 'fractional', 'section-exponential')">
                            <textarea id="fractional-answer" name="answer_text" rows="4" placeholder="Show your step-by-step working..."></textarea>
                            <input type="hidden" name="exercise_id" value="102">
                            <button type="submit" name="submit_digital" class="btn">Submit &amp; Unlock Next</button>
                        </form>
                        <div id="fractional-feedback" class="submit-status"></div>
                    </div>
                </div>
            </div>

            <!-- ============ SECTION 3: EXPONENTIAL EQUATIONS (LOCKED) ============ -->
            <div id="section-exponential-wrapper" class="locked-section-wrapper">
                <div class="locked-placeholder">
                    <span class="lock-icon">🔒</span>
                    <strong>Section Locked</strong><br>
                    Complete <strong>Exercise 2</strong> above to unlock Exponential Equations.
                </div>
                <div class="real-content">
                    <h2>⚡ Exponential Equations</h2>
                    <div class="definition-box">
                        <p>An <span class="highlight">exponential equation</span> is an equation where the unknown variable is in the exponent (power).</p>
                    </div>
                    <h3>Method to Solve Exponential Equations</h3>
                    <ol>
                        <li>Express both sides of the equation as powers of the <strong>same base</strong>.</li>
                        <li>Equate the exponents.</li>
                        <li>Solve the resulting linear equation.</li>
                    </ol>
                    <div class="note-box">
                        <h4>Example 1 (With Steps):</h4>
                        <p>Solve \( 2^x = 16 \)</p>
                        <ol>
                            <li><strong>Write 16 as a power of 2:</strong> \( 16 = 2^4 \)</li>
                            <li><strong>Rewrite the equation:</strong> \( 2^x = 2^4 \)</li>
                            <li><strong>Equate the exponents:</strong> \( x = 4 \)</li>
                        </ol>
                    </div>
                    <div class="note-box">
                        <h4>Example 2 (With Steps):</h4>
                        <p>Solve \( 8^{x-1} = 16 \)</p>
                        <ol>
                            <li><strong>Write both sides in base 2:</strong> \( (2^3)^{x-1} = 2^4 \)</li>
                            <li><strong>Simplify the left side:</strong> \( 2^{3(x-1)} = 2^4 \)</li>
                            <li><strong>Equate exponents:</strong> \( 3x - 3 = 4 \)</li>
                            <li><strong>Solve for \(x\):</strong> \( 3x = 7 \Rightarrow x = \frac{7}{3} \)</li>
                        </ol>
                    </div>

                    <div class="exercise-block" id="exponential-exercise">
                        <h3>✏️ Exercise 3: Solving Exponential Equations</h3>
                        <p>Solve for \( x \):</p>
                        <ol>
                            <li>\( 2^x = 32 \)</li>
                            <li>\( 3^{x+2} = 27 \)</li>
                            <li>\( 4^{2x-1} = 8^{x+1} \)</li>
                            <li>\( 5^{x} = 0.04 \)</li>
                            <li>\( 9^{x} \times 3^{x+1} = 27^{2x} \)</li>
                        </ol>
                        <form id="form-exponential" onsubmit="submitExercise(event, 'exponential', 'section-logarithms')">
                            <textarea id="exp-answer" name="answer_text" rows="5" placeholder="Show your working clearly..."></textarea>
                            <input type="hidden" name="exercise_id" value="103">
                            <button type="submit" name="submit_digital" class="btn">Submit &amp; Unlock Next</button>
                        </form>
                        <div id="exp-feedback" class="submit-status"></div>
                    </div>
                </div>
            </div>

            <!-- ============ SECTION 4: LOGARITHMS (LOCKED) ============ -->
            <div id="section-logarithms-wrapper" class="locked-section-wrapper">
                <div class="locked-placeholder">
                    <span class="lock-icon">🔒</span>
                    <strong>Section Locked</strong><br>
                    Complete <strong>Exercise 3</strong> above to unlock Logarithms.
                </div>
                <div class="real-content">
                    <h2>📊 Introduction to Logarithms</h2>
                    <div class="definition-box">
                        <p>The <span class="highlight">logarithm</span> of a number \(x\) to a base \(a\) is the power to which \(a\) must be raised to give \(x\).</p>
                        <div class="formula-box">
                            \[
                            \log_{a} x = y \iff a^{y} = x
                            \]
                        </div>
                        <p>where \(a > 0, a \neq 1\) and \(x > 0\).</p>
                    </div>
                    <div class="note-box">
                        <h4>📌 Special Logarithms</h4>
                        <ul>
                            <li><strong>Base 10</strong> is often written simply as \( \log x \) (common logarithm).</li>
                            <li><strong>Base \(e\)</strong> (Euler's number) is denoted \( \ln x \) (natural logarithm).</li>
                        </ul>
                    </div>
                    <div class="definition-box">
                        <h4>📌 Basic Rules:</h4>
                        <ol>
                            <li>\( \log_{a} 1 = 0 \)</li>
                            <li>\( \log_{a} a = 1 \)</li>
                            <li>\( \log_{a} (xy) = \log_{a} x + \log_{a} y \)</li>
                            <li>\( \log_{a} \left(\frac{x}{y}\right) = \log_{a} x - \log_{a} y \)</li>
                            <li>\( \log_{a} (x^{n}) = n \log_{a} x \)</li>
                        </ol>
                    </div>

                    <div class="exercise-block" id="log-express-exercise">
                        <h3>✏️ Exercise 4: Expressing as Single Logarithms</h3>
                        <p>Express the following as a single logarithm:</p>
                        <ol>
                            <li>\( \log_{3} 6 + \log_{3} 7 \)</li>
                            <li>\( \log_{2} 48 - \log_{2} 6 \)</li>
                            <li>\( 2\log_{5} 3 + 3\log_{5} 2 \)</li>
                            <li>\( \log_{10} 5 + \log_{10} 6 - \log_{10} 12 \)</li>
                        </ol>
                        <form id="form-log-express" onsubmit="submitExercise(event, 'log-express', 'section-log-equations')">
                            <textarea id="log-answer" name="answer_text" rows="5" placeholder="Use the laws of logarithms to combine terms..."></textarea>
                            <input type="hidden" name="exercise_id" value="104">
                            <button type="submit" name="submit_digital" class="btn">Submit &amp; Unlock Next</button>
                        </form>
                        <div id="log-feedback" class="submit-status"></div>
                    </div>
                </div>
            </div>

            <!-- ============ SECTION 5: LOGARITHMIC EQUATIONS (LOCKED) ============ -->
            <div id="section-log-equations-wrapper" class="locked-section-wrapper">
                <div class="locked-placeholder">
                    <span class="lock-icon">🔒</span>
                    <strong>Section Locked</strong><br>
                    Complete <strong>Exercise 4</strong> above to unlock Logarithmic Equations.
                </div>
                <div class="real-content">
                    <h2>🧩 Solving Logarithmic Equations</h2>
                    <p>To solve logarithmic equations, we use the laws of logarithms to combine terms and then convert to exponential form or equate arguments.</p>

                    <div class="note-box">
                        <h4>Example 1 (With Steps):</h4>
                        <p>Solve \( \log_{5} x = 1 + \log_{5} (x - 4) \)</p>
                        <ol>
                            <li><strong>Move all logs to one side:</strong> \( \log_{5} x - \log_{5} (x - 4) = 1 \)</li>
                            <li><strong>Use the division rule:</strong> \( \log_{5} \left( \frac{x}{x-4} \right) = 1 \)</li>
                            <li><strong>Rewrite \(1\) as a logarithm of base \(5\):</strong> \( \log_{5} \left( \frac{x}{x-4} \right) = \log_{5} 5 \)</li>
                            <li><strong>Equate the arguments:</strong> \( \frac{x}{x-4} = 5 \)</li>
                            <li><strong>Solve for \(x\):</strong> \( x = 5x - 20 \implies 20 = 4x \implies x = 5 \)</li>
                        </ol>
                    </div>

                    <div class="note-box">
                        <h4>Example 2 (With Steps):</h4>
                        <p>Solve \( 2\log_{3} x = \log_{3} (x + 6) \)</p>
                        <ol>
                            <li><strong>Use the power rule:</strong> \( \log_{3} x^{2} = \log_{3} (x + 6) \)</li>
                            <li><strong>Equate the arguments:</strong> \( x^{2} = x + 6 \)</li>
                            <li><strong>Rearrange into a quadratic equation:</strong> \( x^{2} - x - 6 = 0 \)</li>
                            <li><strong>Factorize:</strong> \( (x - 3)(x + 2) = 0 \)</li>
                            <li><strong>Check for validity:</strong> \( x = 3 \) is valid; \(x=-2\) is invalid because \(\log_{3} (-2)\) is undefined.</li>
                        </ol>
                    </div>

                    <div class="exercise-block" id="log-eq-exercise">
                        <h3>✏️ Exercise 5: Solving Logarithmic Equations</h3>
                        <p>Solve for \(x\):</p>
                        <ol>
                            <li>\( \log_{2} x = -3 \)</li>
                            <li>\( \log_{4} (x - 2) = 3 \)</li>
                            <li>\( \log_{x} 25 = 2 \)</li>
                            <li>\( \log_{2} (x + 1) + \log_{2} (x - 1) = 3 \)</li>
                        </ol>
                        <form id="form-log-eq" onsubmit="submitExercise(event, 'log-eq', 'section-applications')">
                            <textarea id="log-eq-answer" name="answer_text" rows="5" placeholder="Show your working step by step..."></textarea>
                            <input type="hidden" name="exercise_id" value="105">
                            <button type="submit" name="submit_digital" class="btn">Submit &amp; Unlock Next</button>
                        </form>
                        <div id="log-eq-feedback" class="submit-status"></div>
                    </div>
                </div>
            </div>

            <!-- ============ SECTION 6: REAL-LIFE APPLICATIONS (LOCKED) ============ -->
            <div id="section-applications-wrapper" class="locked-section-wrapper">
                <div class="locked-placeholder">
                    <span class="lock-icon">🔒</span>
                    <strong>Section Locked</strong><br>
                    Complete <strong>Exercise 5</strong> above to unlock Real-Life Applications.
                </div>
                <div class="real-content">
                    <h2>🌍 Real-Life Applications</h2>

                    <div class="note-box">
                        <h4>Population Growth</h4>
                        <p>A tree frog population doubles every three weeks. If there are initially 10 frogs, the population after \(n\) weeks is:</p>
                        <div class="formula-box">
                            \[
                            P(n) = 10 \times 2^{\frac{n}{3}}
                            \]
                        </div>
                        <p>How long will it take for the population to reach 10,240?</p>
                        <p><strong>Solution:</strong></p>
                        <p>\[
                        10 \times 2^{\frac{n}{3}} = 10{,}240
                        \]</p>
                        <p>\[
                        2^{\frac{n}{3}} = 1{,}024
                        \]</p>
                        <p>Since \(1{,}024 = 2^{10}\):</p>
                        <p>\[
                        \frac{n}{3} = 10 \implies n = 30 \text{ weeks}
                        \]</p>
                    </div>

                    <div class="note-box">
                        <h4>pH Scale</h4>
                        <p>Logarithms are used to describe the concentration of hydrogen ions \([H^+]\) in a solution.</p>
                        <div class="formula-box">
                            \[
                            pH = -\log_{10} [H^{+}]
                            \]
                        </div>
                    </div>

                    <div class="note-box">
                        <h4>Richter Scale (Earthquake Magnitude)</h4>
                        <p>The Moment Magnitude Scale (MMS), also called the Richter Scale, is a logarithmic scale used to measure the magnitude of earthquakes.</p>
                        <div class="formula-box">
                            \[
                            M = \frac{2}{3} \log\left(\frac{S}{S_0}\right)
                            \]
                        </div>
                    </div>

                    <div class="note-box">
                        <h4>Newton's Law of Cooling</h4>
                        <p>When a hot object is left in surrounding air, its temperature decreases exponentially.</p>
                        <div class="formula-box">
                            \[
                            T(t) = T_s + (T_0 - T_s) e^{-kt}
                            \]
                        </div>
                    </div>

                    <!-- FINAL ASSIGNMENTS -->
                    <h2>📝 Final Assignments</h2>
                    <div class="assignment">
                        <h3>Assignment 1: Real-World Modelling</h3>
                        <p><strong>Scenario:</strong> A culture of bacteria doubles every 4 hours. Initially, there are 500 bacteria. How many bacteria will be present after 24 hours?</p>
                        <p><em>Write your full working, including the exponential equation and the final answer, in the space below.</em></p>
                        <form onsubmit="event.preventDefault(); alert('Assignment submitted! This will be marked by your teacher.');">
                            <textarea id="assignment-answer" rows="6" placeholder="Write your solution here..."></textarea>
                            <button type="submit" class="btn btn-secondary">📤 Submit Assignment</button>
                        </form>
                    </div>
                    <div class="assignment">
                        <h3>Assignment 2: Logarithmic Equations</h3>
                        <p>Solve the equation: \( \log_{3} (x+2) - \log_{3} (x-1) = 1 \). Provide your answer in exact form and rounded to two decimal places.</p>
                        <form onsubmit="event.preventDefault(); alert('Assignment submitted!');">
                            <textarea id="assignment-log-answer" rows="4" placeholder="Show your working..."></textarea>
                            <button type="submit" class="btn btn-secondary">📤 Submit Assignment</button>
                        </form>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <p style="color: var(--success); font-weight: bold;">🎉 You have completed all exercises and assignments!</p>
                        <a href="library.php" class="btn" style="background: var(--primary);">Back to Library</a>
                    </div>
                </div>
            </div>

        </div>
        <!-- ============ END OF INTERACTIVE NOTE CONTENT ============ -->

    </div>

    <?php
    // Existing quizzes and exercise display logic...
    $quiz = $conn->query("SELECT id FROM quizzes WHERE note_id = $note_id LIMIT 1")->fetch_assoc();
    if ($quiz && is_content_unlocked('quiz', $quiz['id'], $uid)):
        $attempt = $conn->query("SELECT id, status FROM quiz_attempts WHERE user_id = $uid AND quiz_id = {$quiz['id']} LIMIT 1")->fetch_assoc();
        $quiz_link = ($attempt && $attempt['status'] == 'submitted') ? "quiz_results.php?quiz_id={$quiz['id']}" : "take_quiz.php?quiz_id={$quiz['id']}";
        $button_text = ($attempt && $attempt['status'] == 'submitted') ? "View Quiz Results" : "Take Quiz";
    ?>
        <div class="card" style="margin: 2rem 0; background: #f0f7ff; border-left: 5px solid var(--accent);">
            <h3>📌 Test Your Understanding</h3>
            <p>Take the quiz to check if you truly understand this topic. You can take it anytime.</p>
            <a href="<?= $quiz_link ?>" class="btn"><?= $button_text ?></a>
        </div>
    <?php endif; ?>

    <h2>📝 Exercises</h2>
    <?php if (isset($error)) echo "<div class='error'>$error</div>"; 
          if (isset($success)) echo "<div class='success'>$success</div>";
          if ($msg) echo "<div class='success'>$msg</div>"; ?>

    <?php while($ex = $exercises->fetch_assoc()): ?>
    <div class="exercise" style="background:var(--card-bg); padding:1.5rem; border-radius:1rem; margin-bottom:1.5rem; box-shadow:var(--card-shadow);">
        <strong>Exercise <?=$ex['sort_order']?></strong> (<?=$ex['points']?> pts)<br>
        <?=nl2br(htmlspecialchars($ex['question']))?>
        
        <?php if ($ex['status'] == 'marked'): ?>
            <div class="student-answer" style="background:var(--card-alt-bg); padding:1rem; border-radius:0.5rem; margin-top:0.5rem;">
                <strong>Your answer:</strong><br>
                <?=nl2br(htmlspecialchars($ex['answer_text']))?>
                <?php if ($ex['answer_file_path']): ?>
                    <br><a href="download.php?type=exercise&file=<?=urlencode(basename($ex['answer_file_path']))?>" target="_blank">View uploaded file</a>
                <?php endif; ?>
                <br><strong>Marked:</strong> <?=$ex['marks_awarded']?>/<?=$ex['points']?> points
                <br><strong>Feedback:</strong> <?=htmlspecialchars($ex['feedback'])?>
            </div>
        <?php elseif ($ex['status'] == 'paper_pending'): ?>
            <div class="warning" style="margin-top:0.5rem;">
                ✅ You promised to submit this exercise on paper. Deadline: <?=date('Y-m-d H:i:s', strtotime($ex['promised_at'].' +24 hours'))?><br>
                Please bring your written answer to the admin.
            </div>
        <?php elseif (!empty($ex['answer_text']) || !empty($ex['answer_file_path'])): ?>
            <div class="student-answer" style="background:var(--card-alt-bg); padding:1rem; border-radius:0.5rem; margin-top:0.5rem;">
                <strong>Your answer:</strong><br>
                <?=nl2br(htmlspecialchars($ex['answer_text']))?>
                <?php if ($ex['answer_file_path']): ?>
                    <br><a href="download.php?type=exercise&file=<?=urlencode(basename($ex['answer_file_path']))?>" target="_blank">View uploaded file</a>
                <?php endif; ?>
                <br><em>Waiting for marking.</em>
            </div>
        <?php else: ?>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                <form method="post" enctype="multipart/form-data" style="flex:1;">
                    <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
                    <div class="form-group"><label>Your answer (text)</label><textarea name="answer_text" rows="2"></textarea></div>
                    <div class="form-group"><label>OR upload file (image, PDF, text)</label><input type="file" name="answer_file" accept=".jpg,.png,.pdf,.txt"></div>
                    <button type="submit" name="submit_digital">Submit Digital Answer</button>
                </form>
                <form method="post" style="flex:0;">
                    <input type="hidden" name="exercise_id" value="<?=$ex['id']?>">
                    <button type="submit" name="submit_paper" class="btn btn-secondary" style="background:#f39c12;">I will submit on paper</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
</div>
<?php include_once 'includes/footer.php'; ?>
<script>
    const currentNoteId = <?php echo $note_id; ?>;

    function unlockSection(sectionId) {
        const wrapper = document.getElementById(sectionId);
        if (wrapper) {
            wrapper.classList.add('unlocked');
        }
    }

    function submitExercise(event, exerciseKey, nextSectionId) {
        event.preventDefault();
        const form = event.target;
        const textarea = form.querySelector('textarea');
        const feedbackDiv = document.getElementById(exerciseKey + '-feedback');

        if (!textarea.value.trim() || textarea.value.trim().length < 5) {
            if (feedbackDiv) {
                feedbackDiv.innerHTML = '❌ Please write a full working for all questions.';
                feedbackDiv.style.color = '#ef4444';
            }
            return;
        }

        if (feedbackDiv) {
            feedbackDiv.innerHTML = '⏳ Submitting...';
            feedbackDiv.style.color = '#f59e0b';
        }

        const formData = new FormData(form);
        formData.append('submit_digital', '1');

        fetch('student_view_note.php?id=' + currentNoteId, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Look for the "Digital answer submitted!" success message from the PHP handler
            if (data.includes('Digital answer submitted!') || data.includes('success')) {
                feedbackDiv.innerHTML = '✅ Exercise submitted successfully! You may proceed.';
                feedbackDiv.style.color = '#22c55e';
                unlockSection(nextSectionId);
                if (window.MathJax) {
                    MathJax.typesetPromise();
                }
            } else {
                feedbackDiv.innerHTML = '❌ Submission failed. Please try again.';
                feedbackDiv.style.color = '#ef4444';
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            feedbackDiv.innerHTML = '❌ Network error. Please check your connection and try again.';
            feedbackDiv.style.color = '#ef4444';
        });
    }

    mermaid.initialize({startOnLoad:true});
</script>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body></html>