<?php
require_once 'check_remember_me.php';
require_once 'config.php';
session_start();

$admin_hash = function_exists('getAdminHash') ? getAdminHash() : (defined('ADMIN_HASH') ? ADMIN_HASH : '$2y$12$mQu7vfNTUfh5cSoif6Gjje6zLtc2RtDFphO.rVMs/kfn75Q92PTcu');
if (!isset($_SESSION['admin_logged'])) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
        header('WWW-Authenticate: Basic realm="SMART Tutor Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Access denied';
        exit;
    }
    $_SESSION['admin_logged'] = true;
    $_SESSION['role'] = 'admin';
    unset($_SESSION['user_id']);
}

$conn = getDB();

// Create table if it doesn't exist (safety)
$conn->query("
    CREATE TABLE IF NOT EXISTS group_content_locks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        content_type VARCHAR(50) NOT NULL,
        content_id INT NOT NULL,
        is_locked TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_lock (group_id, content_type, content_id),
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
    )
");

// Handle AJAX toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    
    if ($action === 'toggle_lock') {
        $group_id = (int)$_POST['group_id'];
        $content_type = $conn->real_escape_string($_POST['content_type']);
        $content_id = (int)$_POST['content_id'];
        $current = $conn->query("SELECT is_locked FROM group_content_locks WHERE group_id = $group_id AND content_type = '$content_type' AND content_id = $content_id");
        if ($current->num_rows > 0) {
            $row = $current->fetch_assoc();
            $new_lock = $row['is_locked'] ? 0 : 1;
            $conn->query("UPDATE group_content_locks SET is_locked = $new_lock WHERE group_id = $group_id AND content_type = '$content_type' AND content_id = $content_id");
        } else {
            $new_lock = 1; // Default to locked if not set
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) VALUES ($group_id, '$content_type', $content_id, $new_lock)");
        }
        echo json_encode(['success' => true, 'is_locked' => $new_lock]);
        exit;
    }
}

// Filter parameters
$content_type = isset($_GET['content_type']) ? $conn->real_escape_string($_GET['content_type']) : 'note';
$content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;
$selected_class = isset($_GET['class_level']) ? $conn->real_escape_string($_GET['class_level']) : 'Form 3';
$selected_route = isset($_GET['route']) ? $conn->real_escape_string($_GET['route']) : 'sciences';

// Fetch all groups with their lock status for this content
$groups = $conn->query("
    SELECT g.id, g.group_number, g.class_level, g.route, 
           COALESCE(l.is_locked, 0) as is_locked
    FROM groups g
    LEFT JOIN group_content_locks l ON g.id = l.group_id AND l.content_type = '$content_type' AND l.content_id = $content_id
    WHERE g.class_level = '$selected_class' AND g.route = '$selected_route'
    ORDER BY g.group_number
");
?>
<!DOCTYPE html>
<html><head>
    <title>Group Content Locks</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .lock-manager { padding: 1rem; background: var(--card-alt-bg); border-radius: 0.75rem; margin: 1rem 0; }
        .lock-toggle { cursor: pointer; background: var(--accent); color: #1e293b; border: none; padding: 4px 12px; border-radius: 20px; font-weight: 600; }
        .lock-toggle.locked { background: var(--error); color: white; }
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; margin: 1rem 0; }
        .filters select, .filters input { padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #ccc; }
        .filters button { background: var(--accent); color: #1e293b; border: none; padding: 0.5rem 1.5rem; border-radius: 0.5rem; cursor: pointer; }
        .filters button:hover { background: var(--accent-dark); }
    </style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<div class="container">

    <h1>🔒 Group Content Lock Manager</h1>
    <p>Manage which groups can view a specific note, book, or other content.</p>

    <!-- Filters -->
    <div class="filters">
        <label>Content Type:
            <select id="contentTypeFilter">
                <option value="note" <?= $content_type == 'note' ? 'selected' : '' ?>>Note</option>
                <option value="book" <?= $content_type == 'book' ? 'selected' : '' ?>>Book</option>
                <option value="exam" <?= $content_type == 'exam' ? 'selected' : '' ?>>Exam</option>
            </select>
        </label>
        <label>Content ID:
            <input type="number" id="contentIdFilter" value="<?= $content_id ?>" min="1">
        </label>
        <label>Class:
            <select id="classFilter">
                <option value="Form 3" <?= $selected_class == 'Form 3' ? 'selected' : '' ?>>Form 3</option>
                <option value="Form 4" <?= $selected_class == 'Form 4' ? 'selected' : '' ?>>Form 4</option>
            </select>
        </label>
        <label>Route:
            <select id="routeFilter">
                <option value="sciences" <?= $selected_route == 'sciences' ? 'selected' : '' ?>>Sciences</option>
                <option value="humanities" <?= $selected_route == 'humanities' ? 'selected' : '' ?>>Humanities</option>
            </select>
        </label>
        <button id="loadLocksBtn">Load Locks</button>
    </div>

    <!-- Lock Manager -->
    <div class="lock-manager">
        <h3>🔒 Group Access Control</h3>
        <p>Toggle lock/unlock for each group. <strong>Locked</strong> = group cannot see this content. <strong>Unlocked</strong> = group can see this content.</p>
        <div id="lockManagerContent">
            <table class="data-table">
                <thead>
                    <tr><th>Group</th><th>Route</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php while ($g = $groups->fetch_assoc()): ?>
                    <tr>
                        <td>Group <?= $g['group_number'] ?></td>
                        <td><?= ucfirst($g['route']) ?></td>
                        <td id="status-<?= $g['id'] ?>">
                            <?= $g['is_locked'] ? '🔒 Locked' : '🔓 Unlocked' ?>
                        </td>
                        <td>
                            <button class="lock-toggle <?= $g['is_locked'] ? 'locked' : '' ?>"
                                    data-group="<?= $g['id'] ?>"
                                    data-content-type="<?= $content_type ?>"
                                    data-content-id="<?= $content_id ?>">
                                <?= $g['is_locked'] ? 'Unlock' : 'Lock' ?>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer"><a href="admin_dashboard.php" class="btn-back">← Back</a></div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load locks with current filters
        const loadLocksBtn = document.getElementById('loadLocksBtn');
        const contentTypeFilter = document.getElementById('contentTypeFilter');
        const contentIdFilter = document.getElementById('contentIdFilter');
        const classFilter = document.getElementById('classFilter');
        const routeFilter = document.getElementById('routeFilter');

        function reloadPage() {
            const contentType = contentTypeFilter.value;
            const contentId = contentIdFilter.value;
            const classLevel = classFilter.value;
            const route = routeFilter.value;
            window.location.href = `admin_group_locks.php?content_type=${contentType}&content_id=${contentId}&class_level=${classLevel}&route=${route}`;
        }

        loadLocksBtn.addEventListener('click', reloadPage);

        // Toggle lock
        document.querySelectorAll('.lock-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const groupId = this.getAttribute('data-group');
                const contentType = this.getAttribute('data-content-type');
                const contentId = this.getAttribute('data-content-id');

                fetch('admin_group_locks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=toggle_lock&group_id=${groupId}&content_type=${contentType}&content_id=${contentId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const statusSpan = document.getElementById(`status-${groupId}`);
                        statusSpan.innerHTML = data.is_locked ? '🔒 Locked' : '🔓 Unlocked';
                        this.innerHTML = data.is_locked ? 'Unlock' : 'Lock';
                        this.classList.toggle('locked', data.is_locked);
                    } else {
                        alert('Error toggling lock');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error toggling lock');
                });
            });
        });
    });
</script>
<a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
</html>