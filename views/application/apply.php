<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Application Form – SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="apply-page">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/progress_tracker.php'; ?>

    <main class="apply-container">
        <?php if (!empty($error)): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="apply.php" id="applyForm" novalidate>
            <?= $csrfField ?? '' ?>

            <div class="form-group">
                <label for="class_level">Which class are you currently in? *</label>
                <select name="class_level" id="class_level" required>
                    <option value="">-- Select --</option>
                    <option value="Form 3">Form 3</option>
                    <option value="Form 4">Form 4</option>
                </select>
            </div>

            <div class="form-group">
                <label for="gender">Gender *</label>
                <select name="gender" id="gender" required>
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div class="form-group">
                <label for="dob">Date of Birth *</label>
                <input type="date" name="dob" id="dob" required max="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label for="school">Current School (full name) *</label>
                <input
                    type="text"
                    name="school"
                    id="school"
                    placeholder="e.g., Ntcheu Secondary School"
                    required
                    minlength="2"
                    maxlength="200"
                    autocomplete="organization"
                >
            </div>

            <div class="form-group">
                <label>Subjects you are currently taking *</label>
                <div style="margin-bottom: 8px;">
                    <button type="button" class="btn-small" onclick="toggleGroup('subjects_taken', true)">Select All</button>
                    <button type="button" class="btn-small" onclick="toggleGroup('subjects_taken', false)">Clear All</button>
                </div>
                <div class="checkbox-group" id="subjects_taken_group">
                    <?php
                    $currentSubjects = explode(', ', $user['subjects'] ?? '');
                    foreach ($allSubjects as $subject):
                        $id = 'subj_' . preg_replace('/[^a-zA-Z0-9]/', '_', $subject);
                    ?>
                        <input
                            type="checkbox"
                            name="subjects_taken[]"
                            value="<?= htmlspecialchars($subject) ?>"
                            id="<?= htmlspecialchars($id) ?>"
                            <?= in_array($subject, $currentSubjects, true) ? 'checked' : '' ?>
                        >
                        <label for="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($subject) ?></label>
                    <?php endforeach; ?>
                </div>
                <small class="help-text">Select all subjects you are studying at school.</small>
            </div>

            <div class="form-group">
                <label>Which subjects do you need assistance with? *</label>
                <div style="margin-bottom: 8px;">
                    <button type="button" class="btn-small" onclick="toggleGroup('subjects_assist', true)">Select All</button>
                    <button type="button" class="btn-small" onclick="toggleGroup('subjects_assist', false)">Clear All</button>
                </div>
                <div class="checkbox-group" id="subjects_assist_group">
                    <?php
                    $assistSubjects = explode(', ', $application['subject_assist'] ?? '');
                    foreach ($coreSubjects as $subject):
                        $id = 'assist_' . preg_replace('/[^a-zA-Z0-9]/', '_', $subject);
                    ?>
                        <input
                            type="checkbox"
                            name="subjects_assist[]"
                            value="<?= htmlspecialchars($subject) ?>"
                            id="<?= htmlspecialchars($id) ?>"
                            <?= in_array($subject, $assistSubjects, true) ? 'checked' : '' ?>
                        >
                        <label for="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($subject) ?></label>
                    <?php endforeach; ?>
                </div>
                <small class="help-text">Select the subjects you struggle with and want help (English, Mathematics, Biology, Physics, Chemistry).</small>
            </div>

            <div class="form-group">
                <label for="ambition">What career do you want to pursue? *</label>
                <input
                    type="text"
                    name="ambition"
                    id="ambition"
                    placeholder="e.g., Doctor, Engineer, Teacher"
                    required
                    minlength="2"
                    maxlength="120"
                >
            </div>

            <div class="form-group">
                <label for="career_reason">Why do you want that career? *</label>
                <textarea
                    name="career_reason"
                    id="career_reason"
                    rows="3"
                    placeholder="Explain your motivation and passion..."
                    required
                    minlength="10"
                    maxlength="2000"
                ></textarea>
            </div>

            <div class="form-group">
                <label for="universitySelect">Which public university do you aim to join? *</label>
                <select name="university" id="universitySelect" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($universities as $uni): ?>
                        <option value="<?= htmlspecialchars($uni) ?>"><?= htmlspecialchars($uni) ?></option>
                    <?php endforeach; ?>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div id="customUniversityDiv" style="display: none;">
                <div class="form-group">
                    <label for="custom_university">Please specify your university/college name *</label>
                    <input
                        type="text"
                        name="custom_university"
                        id="custom_university"
                        placeholder="e.g., University of Livingstonia"
                        minlength="2"
                        maxlength="200"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="why_join">Why do you want to join this group? *</label>
                <textarea
                    name="why_join"
                    id="why_join"
                    rows="3"
                    placeholder="e.g., To improve my grades, to learn with others..."
                    required
                    minlength="10"
                    maxlength="2000"
                ></textarea>
            </div>

            <div class="form-group">
                <label for="targetPoints">What is your target MSCE points? *</label>
                <input
                    type="number"
                    name="target_points"
                    id="targetPoints"
                    min="0"
                    max="20"
                    step="1"
                    placeholder="e.g., 15"
                    required
                >
                <div id="pointsWarning" class="warning" style="display: none; font-size: 0.8rem;">Target points cannot exceed 20.</div>
            </div>

            <div class="declaration">
                <p>By submitting this application, I confirm that all the information I have provided is true and complete. I understand that false or misleading information may result in rejection or dismissal from the group.</p>
            </div>

            <div class="submission-date">
                <label for="submission_date">Date of Submission:</label>
                <input type="date" id="submission_date" value="<?= date('Y-m-d') ?>" readonly>
            </div>

            <button type="submit" class="btn">Submit Application</button>
        </form>
    </main>

    <?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/toc_navigator.php'; ?>

    <script>
        const uniSelect = document.getElementById('universitySelect');
        const customDiv = document.getElementById('customUniversityDiv');
        const customUniInput = document.getElementById('custom_university');

        function toggleCustomUni() {
            const isOther = uniSelect.value === 'Other';
            customDiv.style.display = isOther ? 'block' : 'none';
            customUniInput.required = isOther;
        }

        uniSelect.addEventListener('change', toggleCustomUni);
        toggleCustomUni();

        function toggleGroup(groupName, selectAll) {
            const group = document.getElementById(groupName + '_group');
            if (!group) return;
            group.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = selectAll);
        }

        const targetPoints = document.getElementById('targetPoints');
        const pointsWarning = document.getElementById('pointsWarning');

        if (targetPoints) {
            targetPoints.addEventListener('input', function() {
                const val = parseInt(this.value, 10);
                if (val > 20) {
                    pointsWarning.style.display = 'block';
                    this.setCustomValidity('Target points cannot exceed 20.');
                } else {
                    pointsWarning.style.display = 'none';
                    this.setCustomValidity('');
                }
            });
        }

        const form = document.getElementById('applyForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to submit this application? Once submitted, you cannot edit it until admin responds.')) {
                    e.preventDefault();
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const formFields = document.querySelectorAll('#applyForm input, #applyForm select, #applyForm textarea');
            const storageKey = 'apply_form_data';
            const savedData = sessionStorage.getItem(storageKey);

            if (savedData) {
                const data = JSON.parse(savedData);
                formFields.forEach(field => {
                    if (field.type === 'checkbox') {
                        const values = data[field.name];
                        if (Array.isArray(values) && values.includes(field.value)) {
                            field.checked = true;
                        }
                    } else if (field.tagName === 'SELECT') {
                        const val = data[field.name];
                        if (val) field.value = val;
                    } else if (field.type !== 'submit' && field.type !== 'hidden' && !field.readOnly) {
                        const val = data[field.name];
                        if (val) field.value = val;
                    }
                });
            } else {
                const userData = <?= json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
                const appData = <?= json_encode($application ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                if (userData) {
                    ['class_level', 'gender', 'school', 'dob'].forEach(f => {
                        const field = document.getElementById(f);
                        if (field && userData[f]) field.value = userData[f];
                    });
                }

                if (appData) {
                    ['ambition', 'career_reason', 'university', 'why_join', 'target_points'].forEach(f => {
                        const field = document.getElementById(f === 'university' ? 'universitySelect' : f);
                        if (field && appData[f]) field.value = appData[f];
                    });
                    toggleCustomUni();
                }
            }

            formFields.forEach(field => {
                field.addEventListener('input', saveFormData);
                field.addEventListener('change', saveFormData);
            });

            function saveFormData() {
                const data = {};
                formFields.forEach(field => {
                    if (field.type === 'checkbox') {
                        const boxes = document.querySelectorAll('[name="' + field.name + '"]:checked');
                        data[field.name] = Array.from(boxes).map(cb => cb.value);
                    } else if (field.tagName === 'SELECT' || (field.type !== 'submit' && field.type !== 'hidden' && !field.readOnly)) {
                        data[field.name] = field.value;
                    }
                });
                sessionStorage.setItem(storageKey, JSON.stringify(data));
            }
        });
    </script>
</body>
</html>
