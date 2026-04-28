<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$conn = getDB();
$uid = $_SESSION['user_id'];
$u = $conn->query("SELECT consent_signed FROM users WHERE id=$uid")->fetch_assoc();
if ($u['consent_signed']) {
    die("Already agreed. <a href='dashboard.php'>Dashboard</a>");
}

$success = false;
$signed_by = '';
$signed_date = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree'])) {
    $signed_by = trim($_POST['signed_by']);
    $signed_date = $_POST['signed_date'];
    $conn->query("UPDATE users SET consent_signed=1, consent_signed_at=NOW() WHERE id=$uid");
    $success = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Consent Form</title>
    <link rel="stylesheet" href="style.css">
    <!-- jsPDF library for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
    <?php include_once 'includes/header.php';
    <?php include_once 'includes/progress_tracker.php'; ?>
 ?>

<div class="consent-container">
    <?php if ($success): ?>
        <div class="success-card" id="successCard">
            <h1>✅ Agreement Signed Successfully</h1>
            <p>Thank you, <strong><?= htmlspecialchars($signed_by) ?></strong>. You confirmed your commitment on <strong><?= htmlspecialchars($signed_date) ?></strong>.</p>
            <div class="success-actions">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <button onclick="printConsent()" class="btn-secondary">🖨️ Print Copy</button>
                <button onclick="downloadPDF()" class="btn-secondary">📄 Download PDF</button>
            </div>
        </div>
        <script>
            function printConsent() {
                const content = document.getElementById('successCard').innerHTML;
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write('<html><head><title>Consent Agreement</title></head><body>');
                printWindow.document.write(content);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.print();
            }

            async function downloadPDF() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                doc.setFontSize(14);
                doc.text("Consent Agreement Confirmation", 20, 20);
                doc.setFontSize(12);
                doc.text("This document certifies that the student has signed and agreed to the commitments and rules.", 20, 35);
                doc.text("Signed by: <?= addslashes($signed_by) ?>", 20, 50);
                doc.text("Date of Agreement: <?= addslashes($signed_date) ?>", 20, 65);
                doc.text("Signature: ___________________________", 20, 80);
                doc.save("Consent_Agreement_<?= preg_replace('/[^a-zA-Z0-9]/','_', $signed_by) ?>.pdf");
            }
        </script>
    <?php else: ?>
        <h1>📜 Formal Consent Agreement</h1>
        <p>By signing this consent, I solemnly acknowledge and agree to the following commitments:</p>

        <h2>Commitments</h2>
        <ul class="agreement-list">
            <li>I will work hard and read extensively to improve my knowledge.</li>
            <li>I will be punctual for all sessions and respect the agreed schedule.</li>
            <li>I will respect my class teacher and peers at all times.</li>
            <li>I will not rely only on past papers but will engage fully with all learning materials.</li>
            <li>I will not engage in any financial or inappropriate exchanges. I understand this leads to dismissal.</li>
        </ul>

        <h2>Consequences of Breach</h2>
        <ul class="agreement-list">
            <li>A warning may be issued for minor violations.</li>
            <li>Extra assignments may be given as corrective measures.</li>
            <li>Suspension may occur, during which my content access will be locked.</li>
            <li>Permanent dismissal will result from serious or repeated violations.</li>
        </ul>

        <form method="post" class="consent-form">
            <div class="form-group">
                <label><input type="checkbox" name="agree" required> I hereby agree to abide by all rules and commitments stated above.</label>
            </div>

            <!-- Signature Section -->
            <div class="signature-section">
                <h3>Signature</h3>
                <div class="signature-line">
                    <label for="signed_by">Signed by:</label>
                    <input type="text" id="signed_by" name="signed_by" placeholder="Your full name" required>
                </div>
                <div class="signature-line">
                    <label for="signed_date">Date:</label>
                    <input type="date" id="signed_date" name="signed_date" required>
                </div>
            </div>

            <button type="submit" class="btn">Accept & Continue</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
