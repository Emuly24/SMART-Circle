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

// Submit late reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reason'])) {
    $date = $_POST['date'];
    $reason = trim($_POST['reason']);
    if (!empty($reason)) {
        $stmt = $conn->prepare("UPDATE attendance SET late_reason = ? WHERE user_id = ? AND date = ?");
        $stmt->bind_param("sis", $reason, $uid, $date);
        $stmt->execute();
        echo "<script>alert('Late reason submitted. Thank you.');</script>";
    }
}

// Fetch attendance records (last 30 days)
$att_stmt = $conn->prepare("SELECT date, status, arrival_time, remarks, late_reason FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 30");
$att_stmt->bind_param("i", $uid);
$att_stmt->execute();
$att = $att_stmt->get_result();

$disc_stmt = $conn->prepare("SELECT action, reason, suspension_end, created_at FROM discipline_log WHERE user_id = ? ORDER BY created_at DESC");
$disc_stmt->bind_param("i", $uid);
$disc_stmt->execute();
$disc = $disc_stmt->get_result();
?>
<!DOCTYPE html><html><head><title>Attendance & Discipline</title><link rel="stylesheet" href="style.css"></head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <h2>Your Attendance (last 30 days)</h2>
        <table class="data-table">
            <thead><tr><th>Date</th><th>Status</th><th>Arrival Time</th><th>Admin Remarks</th><th>Your Late Reason</th><th>Action</th></tr></thead>
            <tbody>
            <?php while($r = $att->fetch_assoc()): 
                $status_display = match($r['status']) {
                    'on_time' => '✅ On time',
                    'late' => '⏰ Late',
                    'absent' => '❌ Absent',
                    default => ucfirst($r['status'])
                };
            ?>
                <tr>
                    <td><?= $r['date'] ?></td>
                    <td><?= $status_display ?></td>
                    <td><?= $r['arrival_time'] ? date('h:i A', strtotime($r['arrival_time'])) : '—' ?></td>
                    <td><?= nl2br(htmlspecialchars($r['remarks'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($r['late_reason'])) ?></td>
                    <td>
                        <?php if ($r['status'] == 'late' && empty($r['late_reason'])): ?>
                            <form method="post" style="display:flex; gap:5px; flex-direction:column;">
                                <input type="hidden" name="date" value="<?= $r['date'] ?>">
                                <textarea name="reason" rows="2" placeholder="Explain why you were late..." required></textarea>
                                <button type="submit" name="submit_reason" class="btn-small">Submit Reason</button>
                            </form>
                        <?php elseif ($r['status'] == 'late' && !empty($r['late_reason'])): ?>
                            <em>Reason submitted</em>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <h2>Discipline History</h2>
        <table class="data-table">
            <thead><tr><th>Date</th><th>Action</th><th>Reason</th><th>Suspension End</th></tr></thead>
            <tbody>
            <?php while($d = $disc->fetch_assoc()): ?>
                <tr>
                    <td><?= $d['created_at'] ?></td>
                    <td><?= strtoupper($d['action']) ?></td>
                    <td><?= htmlspecialchars($d['reason']) ?></td>
                    <td><?= $d['suspension_end'] ?? '-' ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php include_once 'includes/footer.php'; ?>
        <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>