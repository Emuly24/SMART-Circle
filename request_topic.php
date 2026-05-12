<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
require_once 'topics_data.php';

$conn = getDB();
$uid = $_SESSION['user_id'];
$class = $_SESSION['class_level']; // "Form 3" or "Form 4"

$subjects = ['Mathematics', 'English', 'Biology', 'Physics', 'Chemistry'];

$error = '';
$success = '';

// Fetch existing topics for this user (to pre-fill)
$existing = [];
$res = $conn->query("SELECT subject, topic FROM topic_requests WHERE user_id = $uid");
while ($r = $res->fetch_assoc()) {
    $existing[$r['subject']][] = $r['topic'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_requests'])) {
        $subject = $_POST['subject'];
        $topics = isset($_POST['topics']) ? $_POST['topics'] : [];
        
        if (empty($topics)) {
            $error = "Please select at least one topic.";
        } else {
            $conn->query("DELETE FROM topic_requests WHERE user_id = $uid AND subject = '$subject'");
            $stmt = $conn->prepare("INSERT INTO topic_requests (user_id, subject, topic, class_level) VALUES (?, ?, ?, ?)");
            foreach ($topics as $topic) {
                if (!empty($topic)) {
                    $stmt->bind_param("isss", $uid, $subject, $topic, $class);
                    $stmt->execute();
                }
            }
            $success = count($topics) . " topic(s) requested for $subject. Admin will review them.";
            $existing[$subject] = $topics;
        }
    } elseif (isset($_POST['check_topic'])) {
        $subject = $_POST['subject'];
        $topic = trim($_POST['single_topic']);
        if (empty($topic)) {
            $error = "Enter a topic to check.";
        } else {
            $check = $conn->query("SELECT covered_date FROM topics_covered WHERE class_level='$class' AND subject='$subject' AND topic='$topic'");
            if ($check && $check->num_rows) {
                $covered_date = $check->fetch_assoc()['covered_date'];
                $error = "⚠️ This topic was covered on $covered_date. You can still request it but priority may be lower.";
            } else {
                $error = "✅ This topic is new. You can request it by adding it to the list above.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html><head><title>Request Topic</title><link rel="stylesheet" href="style.css"></head>
<body>
    <?php include_once 'includes/header.php'; ?>
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-lightbulb"></i> Request Topics to be Covered</h2>
            <p>Select a subject, then choose the topics you want to request. You can select as many as you need.</p>
            <p><a href="covered_topics.php">📜 View already covered topics</a></p>
            <?php if ($error): ?>
                <div class="error"><?= nl2br(htmlspecialchars($error)) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="post" id="topicForm">
                <div class="form-group">
                    <label>Select Subject</label>
                    <select name="subject" id="subjectSelect" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>" <?= (isset($_POST['subject']) && $_POST['subject'] == $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="topicContainer">
                    <label>Select Topics (check the ones you want)</label>
                    <div id="loadingMessage" class="loading-message" style="text-align: center; color: var(--text-muted); display: none;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
                    </div>
                    <div id="topicList" class="topic-list-container" style="display: none;">
                        <!-- Topics will be loaded here as beautifully styled checkboxes -->
                    </div>
                </div>
                <button type="submit" name="save_requests" class="btn">Submit Request</button>
            </form>
            
            <hr>
            <h3>Check if a topic has already been covered</h3>
            <form method="post" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <div style="flex:1;">
                    <label>Subject</label>
                    <select name="subject">
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:2;">
                    <label>Topic</label>
                    <input type="text" name="single_topic" placeholder="e.g., Quadratic Equations">
                </div>
                <button type="submit" name="check_topic" class="btn-secondary">Check</button>
            </form>
        </div>
        <?php include_once 'includes/footer.php'; ?>
<?php include_once 'includes/toc_navigator.php'; ?>
    <!-- ======================= STYLING ======================= -->
    <style>
        .topic-list-container {
            border: 1px solid var(--card-alt-bg);
            background: var(--card-bg);
            border-radius: 8px;
            padding: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* Make each topic option a full-width, clickable pill */
        .topic-option-wrapper {
            margin: 8px 0;
        }
        
        .topic-option-wrapper .distinct-checkbox {
            width: 100%;
            margin: 0;
            padding: 0.8rem 1rem;
            border-radius: 0.8rem; /* Slightly less rounded than buttons */
            background: var(--card-alt-bg);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topic-option-wrapper .distinct-checkbox:hover {
            transform: translateX(4px);
            background: var(--card-bg);
            border-color: var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .topic-option-wrapper .distinct-checkbox input[type="checkbox"] {
            /* Ensure the checkbox is visually distinct */
            flex-shrink: 0;
            margin-right: 0;
        }
        
        .topic-option-wrapper .distinct-checkbox span {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-color);
        }
    </style>

    <!-- ======================= JAVASCRIPT ======================= -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const subjectSelect = document.getElementById('subjectSelect');
            const topicList = document.getElementById('topicList');
            const loadingMsg = document.getElementById('loadingMessage');
            const existingTopics = <?= json_encode($existing) ?>;

            // Load topics when subject changes
            subjectSelect.addEventListener('change', function() {
                const subject = this.value;
                topicList.innerHTML = '';
                topicList.style.display = 'none';
                loadingMsg.style.display = 'block';

                if (!subject) {
                    loadingMsg.style.display = 'none';
                    return;
                }

                const classLevel = '<?= $_SESSION['class_level'] ?>';
                fetch(`get_topics.php?subject=${encodeURIComponent(subject)}&class=${encodeURIComponent(classLevel)}`)
                    .then(res => res.json())
                    .then(data => {
                        loadingMsg.style.display = 'none';
                        topicList.style.display = 'block';
                        
                        if (data.length === 0) {
                            topicList.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No topics available for this subject.</p>';
                            return;
                        }

                        // Create a beautifully styled checkbox for each topic
                        data.forEach(topic => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'topic-option-wrapper';
                            
                            const label = document.createElement('label');
                            label.className = 'distinct-checkbox';

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.name = 'topics[]';
                            checkbox.value = topic;
                            checkbox.id = 'topic_' + topic.replace(/\s+/g, '_');

                            // Pre-check if already requested
                            if (existingTopics[subject] && existingTopics[subject].includes(topic)) {
                                checkbox.checked = true;
                            }

                            const span = document.createElement('span');
                            span.textContent = topic;

                            label.appendChild(checkbox);
                            label.appendChild(span);
                            wrapper.appendChild(label);
                            topicList.appendChild(wrapper);
                        });
                    })
                    .catch(err => {
                        loadingMsg.style.display = 'none';
                        topicList.style.display = 'block';
                        topicList.innerHTML = '<p style="color:var(--error); text-align:center; padding:20px;">Error loading topics. Please refresh.</p>';
                        console.error(err);
                    });
            });

            // Trigger initial load if a subject was selected (e.g., after form submission)
            const initialSubject = subjectSelect.value;
            if (initialSubject) {
                subjectSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>