<?php
require_once 'config.php';
require_once 'cookie_login.php';
$user = $GLOBALS['auth_user'];
$role = $GLOBALS['auth_role'];
$conn = getDB();
$subjects = ['Mathematics', 'Biology', 'English', 'Physics', 'Chemistry'];
$classes = ['Form 3', 'Form 4'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $subj = $_POST['subject'];
    $class = $_POST['class_level'];
    $due = $_POST['due_date'];
    $group_id = isset($_POST['group_id']) && $_POST['group_id'] ? (int)$_POST['group_id'] : 0;
    $attach = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $dir = 'uploads/assignments/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','png','pdf','doc','txt'])) {
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['attachment']['name']);
            $dest = $dir . $name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) $attach = $dest;
        }
    }
    $conn->query("INSERT INTO assignments (title, description, attachment_file_path, subject, class_level, due_date) VALUES ('$title', '$desc', '$attach', '$subj', '$class', '$due')");
    $ass_id = $conn->insert_id;
    
    if ($group_id) {
        $all_groups = $conn->query("SELECT id FROM groups WHERE class_level = '$class'");
        while ($g = $all_groups->fetch_assoc()) {
            $lock = $g['id'] == $group_id ? 0 : 1;
            $conn->query("INSERT INTO group_content_locks (group_id, content_type, content_id, is_locked) 
                          VALUES ({$g['id']}, 'assignment', $ass_id, $lock)
                          ON DUPLICATE KEY UPDATE is_locked = $lock");
        }
        $msg = "Assignment created and unlocked for the selected group.";
    } else {
        $msg = "Assignment created. Use the lock manager to control group access.";
    }
    echo "<script>alert('$msg'); window.location='admin_assignments_list.php';</script>";
    exit;
}
?>
<!DOCTYPE html><html><head><title>Create Assignment</title><link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.4.2/tinymce.min.js"></script>
</head><body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card" style="padding: 2rem;">
            <h2>📝 Create New Assignment</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group"><label>Title</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="editor"></textarea></div>
                <div class="form-group"><label>Attachment (optional)</label><input type="file" name="attachment" accept=".jpg,.png,.pdf,.doc,.txt"></div>
                <div class="form-group"><label>Subject</label>
                    <select name="subject" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Class</label>
                    <select name="class_level" required>
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls ?>"><?= $cls ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Due Date</label><input type="datetime-local" name="due_date" required></div>

                <div class="group-selector" style="margin-top: 1rem;">
                    <h4>🎯 Assign to specific group (optional)</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select id="classFilter" style="min-width: 120px;">
                            <option value="">-- Class --</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?= $cls ?>"><?= $cls ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="routeFilter" style="min-width: 120px;">
                            <option value="">-- Route --</option>
                            <option value="sciences">Sciences</option>
                            <option value="humanities">Humanities</option>
                        </select>
                        <select name="group_id" id="groupSelect" style="min-width: 150px;">
                            <option value="">-- Any group (use locks later) --</option>
                        </select>
                    </div>
                    <small class="help-text">If you select a group, this assignment will be instantly unlocked for that group and locked for others.</small>
                </div>

                <button type="submit" class="btn">Create Assignment</button>
            </form>
        </div>
    </div>
    <script>
        tinymce.init({
            selector: '#editor',
            height: 600,
            menubar: true,
            plugins: 'anchor autolink charmap codesample emoticons image imagetools link lists media searchreplace table visualblocks wordcount code',
            toolbar: 'undo redo | styleselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | casechange | charmap | code | editimage',
            toolbar_sticky: true,
            menubar: 'file edit view insert format tools table',
            content_style: 'body { font-family: Inter, sans-serif; }',
            forced_root_block: false,
            valid_elements: '*[*]',
            extended_valid_elements: 'script[type|src|async],style[type]',
            sanitize: false,
            allow_script_urls: true,
            images_upload_url: 'note_editor_api.php?action=upload_image',
            automatic_uploads: true,
            image_advtab: true,
            image_dimensions: true,
            image_caption: true,
            init_instance_callback: function(editor) {
                document.getElementById('editor').style.display = 'none';
            }
        });

        function loadGroups() {
            const classVal = document.getElementById('classFilter').value;
            const routeVal = document.getElementById('routeFilter').value;
            const groupSelect = document.getElementById('groupSelect');
            if (!classVal || !routeVal) {
                groupSelect.innerHTML = '<option value="">-- Select class and route first --</option>';
                return;
            }
            fetch(`admin_get_groups.php?class=${encodeURIComponent(classVal)}&route=${encodeURIComponent(routeVal)}`)
                .then(res => res.json())
                .then(data => {
                    groupSelect.innerHTML = '<option value="">-- Any group (use locks later) --</option>';
                    data.forEach(group => {
                        groupSelect.innerHTML += `<option value="${group.id}">Group ${group.group_number} (${group.current_members}/5 members)</option>`;
                    });
                })
                .catch(err => console.error(err));
        }
        document.getElementById('classFilter').addEventListener('change', loadGroups);
        document.getElementById('routeFilter').addEventListener('change', loadGroups);
    </script>
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/toc_navigator.php'; ?>
</body></html>