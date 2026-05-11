<?php
require_once 'check_remember_me.php';
require_once 'config.php';
require_once 'check_access.php';
$conn = getDB();
$uid = $_SESSION['user_id'];

$user = $conn->query("SELECT approved, class_level, gender, school, dob, subjects, route FROM users WHERE id=$uid")->fetch_assop();
if ($user['approved']) {
    header("Location: dashboard.php");
    exit;
}

// === Check if already has an application ===
$has_app = $conn->query("SELECT id FROM applications WHERE user_id=$uid")->num_rows > 0;
if ($has_app) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Already Applied</title><link rel="stylesheet" href="style.css"></head>
    <body>
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="container">
        <div class="card">
            <h2>You have already submitted an application</h2>
            <p>Your application is currently under review. Please wait for the admin to respond.</p>
            <p>If this is your friend using your phone, please log out and let them create their own account.</p>
            <div class="card-buttons">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </div>
    </div>
    </body></html>
    <?php
    exit;
}

$application = $conn->query("SELECT * FROM applications WHERE user_id=$uid")->fetch_assoc();
$error = $success = '';

// Load universities from database
$universities = [];
$uni_res = $conn->query("SELECT name FROM universities ORDER BY name");
while ($row = $uni_res->fetch_assoc()) {
    $universities[] = $row['name'];
}
if (empty($universities)) {
    $universities = [
        "University of Malawi (UNIMA)",
        "Mzuzu University (MZUNI)",
        "Lilongwe University of Agriculture and Natural Resources (LUANAR)",
        "Malawi University of Business and Applied Sciences (MUBAS)",
        "Kamuzu University of Health Sciences (KUHeS)",
        "Malawi University of Science and Technology (MUST)",
        "DMI St. John the Baptist University",
        "Catholic University of Malawi"
    ];
}

$all_subjects = [
    'Mathematics', 'English', 'Biology', 'Chichewa', 'Social Studies', 'History',
    'Bible Knowledge', 'Physics', 'Chemistry', 'Agriculture', 'Geography', 'Life Skills'
];
$core_subjects = ['English', 'Mathematics', 'Biology', 'Physics', 'Chemistry'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_level = $_POST['class_level'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $school = trim($_POST['school'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $subjects_taken = isset($_POST['subjects_taken']) ? implode(', ', $_POST['subjects_taken']) : '';
    $subjects_assist = isset($_POST['subjects_assist']) ? implode(', ', $_POST['subjects_assist']) : '';
    $ambition = trim($_POST['ambition'] ?? '');
    $career_reason = trim($_POST['career_reason'] ?? '');
    $university = $_POST['university'] ?? '';
    $custom_university = trim($_POST['custom_university'] ?? '');
    $why_join = trim($_POST['why_join'] ?? '');
    $target_points = (int)($_POST['target_points'] ?? 0);
    
    if ($university === 'Other' && !empty($custom_university)) {
        $university = $custom_university;
        $check = $conn->query("SELECT id FROM universities WHERE name = '$custom_university'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO universities (name) VALUES ('$custom_university')");
        }
    }
    
    $route = null;
    $has_humanities_subjects = (strpos($subjects_taken, 'History') !== false || strpos($subjects_taken, 'Bible Knowledge') !== false || strpos($subjects_taken, 'Social Studies') !== false || strpos($subjects_taken, 'Life Skills') !== false);
    $has_science_subjects = (strpos($subjects_taken, 'Physics') !== false && strpos($subjects_taken, 'Chemistry') !== false);

    if ($has_humanities_subjects && !$has_science_subjects) {
        $route = 'humanities';
    } elseif ($has_science_subjects && !$has_humanities_subjects) {
        $route = 'sciences';
    } elseif ($has_humanities_subjects && $has_science_subjects) {
        $route = 'sciences';
    } else {
        $route = 'sciences';
    }
    
    if (empty($class_level) || empty($gender) || empty($school) || empty($dob) || empty($subjects_taken) || empty($subjects_assist) || empty($ambition) || empty($career_reason) || empty($university) || empty($why_join)) {
        $error = "Please fill all required fields.";
    } elseif ($target_points > 20) {
        $error = "🌟 Your target points ($target_points) are above 20. We believe you can aim for ≤20. Please adjust and resubmit.";
    } else {
        $conn->query("UPDATE users SET class_level = '$class_level', gender = '$gender', school = '$school', dob = '$dob', subjects = '$subjects_taken', route = '$route' WHERE id = $uid");
        
        $seriousness = json_encode(['agree' => true]);
        if ($application) {
            $conn->query("UPDATE applications SET 
                ambition='$ambition', 
                career_reason='$career_reason', 
                university='$university', 
                why_join='$why_join', 
                subject_assist='$subjects_assist', 
                target_points=$target_points, 
                seriousness_answers='$seriousness', 
                status='pending', 
                submitted_at = NOW(),
                admin_notes = NULL 
                WHERE user_id=$uid");
        } else {
            $conn->query("INSERT INTO applications (user_id, ambition, career_reason, university, why_join, subject_assist, target_points, seriousness_answers) VALUES ($uid, '$ambition', '$career_reason', '$university', '$why_join', '$subjects_assist', $target_points, '$seriousness')");
        }
        header("Location: pending.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html><head><title>Application Form – SMART Circle</title><link rel="stylesheet" href="style.css"></head><body class="apply-page">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/progress_tracker.php'; ?>
    <div class="apply-container">
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?> <a href="dashboard.php">Back to Dashboard</a></div>
        <?php else: ?>
            <form method="post" id="applyForm">
                <div class="form-group">
                    <label>Which class are you currently in? *</label>
                    <select name="class_level" id="class_level" required>
                        <option value="">-- Select --</option>
                        <option value="Form 3">Form 3</option>
                        <option value="Form 4">Form 4</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" id="gender" required>
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date of Birth *</label>
                    <input type="date" name="dob" id="dob" required>
                </div>

                <div class="form-group">
                    <label>Current School (full name) *</label>
                    <input type="text" name="school" id="school" placeholder="e.g., Ntcheu Secondary School" required>
                </div>

                <!-- SUBJECTS TAKEN (with Select All / Clear All) -->
                <div class="form-group">
                    <label>Subjects you are currently taking *</label>
                    <div style="margin-bottom: 8px;">
                        <button type="button" class="btn-small" onclick="toggleGroup('subjects_taken', true)">Select All</button>
                        <button type="button" class="btn-small" onclick="toggleGroup('subjects_taken', false)">Clear All</button>
                    </div>
                    <div class="checkbox-group" id="subjects_taken_group">
                        <?php $current_subjects = explode(', ', $user['subjects'] ?? ''); ?>
                        <?php foreach ($all_subjects as $s): 
                            $id = "subj_" . preg_replace('/[^a-zA-Z0-9]/', '_', $s);
                        ?>
                            <input type="checkbox" name="subjects_taken[]" value="<?= $s ?>" id="<?= $id ?>">
                            <label for="<?= $id ?>"><?= $s ?></label>
                        <?php endforeach; ?>
                    </div>
                    <small class="help-text">Select all subjects you are studying at school.</small>
                </div>

                <!-- SUBJECTS ASSIST (with Select All / Clear All) -->
                <div class="form-group">
                    <label>Which subjects do you need assistance with? (select all that apply) *</label>
                    <div style="margin-bottom: 8px;">
                        <button type="button" class="btn-small" onclick="toggleGroup('subjects_assist', true)">Select All</button>
                        <button type="button" class="btn-small" onclick="toggleGroup('subjects_assist', false)">Clear All</button>
                    </div>
                    <div class="checkbox-group" id="subjects_assist_group">
                        <?php $assist_subjects = explode(', ', $application['subject_assist'] ?? ''); ?>
                        <?php foreach ($core_subjects as $s):
                            $id = "assist_" . preg_replace('/[^a-zA-Z0-9]/', '_', $s);
                        ?>
                            <input type="checkbox" name="subjects_assist[]" value="<?= $s ?>" id="<?= $id ?>">
                            <label for="<?= $id ?>"><?= $s ?></label>
                        <?php endforeach; ?>
                    </div>
                    <small class="help-text">Select the subjects you struggle with and want help (English, Mathematics, Biology, Physics, Chemistry).</small>
                </div>

                <div class="form-group">
                    <label>What career do you want to pursue? *</label>
                    <input type="text" name="ambition" id="ambition" placeholder="e.g., Doctor, Engineer, Teacher" required>
                </div>

                <div class="form-group">
                    <label>Why do you want that career? *</label>
                    <textarea name="career_reason" id="career_reason" rows="3" placeholder="Explain your motivation and passion..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Which public university do you aim to join? *</label>
                    <select name="university" id="universitySelect" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($universities as $u): ?>
                            <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div id="customUniversityDiv" style="display: none;">
                    <div class="form-group">
                        <label>Please specify your university/college name *</label>
                        <input type="text" name="custom_university" id="custom_university" placeholder="e.g., University of Livingstonia">
                    </div>
                </div>

                <div class="form-group">
                    <label>Why do you want to join this group? *</label>
                    <textarea name="why_join" id="why_join" rows="3" placeholder="e.g., To improve my grades, to learn with others..." required></textarea>
                </div>

                <div class="form-group">
                    <label>What is your target MSCE points? *</label>
                    <input type="number" name="target_points" id="targetPoints" min="0" max="20" placeholder="e.g., 15" required>
                    <div id="pointsWarning" class="warning" style="display: none; font-size: 0.8rem;">⚠️ Target points cannot exceed 20.</div>
                </div>

                <div class="declaration">
                    <p>By submitting this application, I confirm that all the information I have provided is true and complete. I understand that false or misleading information may result in rejection or dismissal from the group.</p>
                </div>

                <div class="submission-date">
                    <label>Date of Submission:</label>
                    <input type="date" value="<?= date('Y-m-d') ?>" readonly>
                </div>

                <button type="submit" class="btn">Submit Application</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="footer"><a href="index.php" class="btn-back">← Back</a></div>
    <a href="#" class="back-to-top" id="backToTop">↑</a>
</body>
<script>
    // 1. Custom university toggle
    const uniSelect = document.getElementById('universitySelect');
    const customDiv = document.getElementById('customUniversityDiv');
    function toggleCustomUni() {
        customDiv.style.display = uniSelect.value === 'Other' ? 'block' : 'none';
    }
    uniSelect.addEventListener('change', toggleCustomUni);
    toggleCustomUni();

    // 2. Select All / Clear All for checkboxes
    function toggleGroup(groupName, selectAll) {
        const group = document.getElementById(groupName + '_group');
        if (!group) return;
        const checkboxes = group.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = selectAll);
    }

    // 3. Real-time validation for target points
    const targetPoints = document.getElementById('targetPoints');
    const pointsWarning = document.getElementById('pointsWarning');
    if (targetPoints) {
        targetPoints.addEventListener('input', function() {
            const val = parseInt(this.value);
            if (val > 20) {
                pointsWarning.style.display = 'block';
                this.setCustomValidity('Target points cannot exceed 20.');
            } else {
                pointsWarning.style.display = 'none';
                this.setCustomValidity('');
            }
        });
        if (parseInt(targetPoints.value) > 20) targetPoints.dispatchEvent(new Event('input'));
    }

    // 4. Confirm before submit
    const form = document.getElementById('applyForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit this application? Once submitted, you cannot edit it until admin responds.')) {
                e.preventDefault();
            }
        });
    }

    // ===== PERSISTENCE: Save & Restore Form Data =====
    document.addEventListener('DOMContentLoaded', function() {
        const formFields = document.querySelectorAll('#applyForm input, #applyForm select, #applyForm textarea');
        const storageKey = 'apply_form_data';

        // 1. Restore data from sessionStorage
        const savedData = sessionStorage.getItem(storageKey);
        if (savedData) {
            const data = JSON.parse(savedData);
            formFields.forEach(field => {
                if (field.type === 'checkbox') {
                    // Checkboxes are handled in a different way (by name array)
                    const values = data[field.name];
                    if (Array.isArray(values) && values.includes(field.value)) {
                        field.checked = true;
                    }
                } else if (field.tagName === 'SELECT') {
                    const val = data[field.name];
                    if (val) {
                        field.value = val;
                    }
                } else if (field.type !== 'submit') {
                    const val = data[field.name];
                    if (val) {
                        field.value = val;
                    }
                }
            });
        } else {
            // Fallback: pre-fill from user signup data (from PHP)
            const userData = <?= json_encode($user) ?>;
            const appData = <?= json_encode($application) ?>;
            
            if (userData) {
                const fields = ['class_level', 'gender', 'school', 'dob'];
                fields.forEach(f => {
                    const field = document.getElementById(f);
                    if (field && userData[f]) {
                        field.value = userData[f];
                    }
                });
            }
            if (appData) {
                const fields = ['ambition', 'career_reason', 'university', 'custom_university', 'why_join', 'target_points'];
                fields.forEach(f => {
                    const field = document.getElementById(f);
                    if (field && appData[f]) {
                        field.value = appData[f];
                    }
                });
            }
        }

        // 2. Save data to sessionStorage on input/change
        formFields.forEach(field => {
            field.addEventListener('input', function() { saveFormData(); });
            field.addEventListener('change', function() { saveFormData(); });
        });

        function saveFormData() {
            const data = {};
            formFields.forEach(field => {
                if (field.type === 'checkbox') {
                    // Store checkboxes as arrays
                    const boxes = document.querySelectorAll(`[name="${field.name}"]:checked`);
                    data[field.name] = Array.from(boxes).map(cb => cb.value);
                } else if (field.tagName === 'SELECT' || field.type !== 'submit') {
                    data[field.name] = field.value;
                }
            });
            sessionStorage.setItem(storageKey, JSON.stringify(data));
        }

       
        form.addEventListener('submit', function() {
        });
    });

    window.addEventListener('beforeunload', function() {
    });
</script>
</html>