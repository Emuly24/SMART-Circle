<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Sign Up - SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="signup-page">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/progress_tracker.php'; ?>

    <main class="signup-container">
        <?php if (!empty($error)): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success" role="status">
                <?= htmlspecialchars($success) ?>
                <a href="login.php">Login now</a>
            </div>
        <?php else: ?>
            <form method="post" action="signup.php" id="signupForm" novalidate>
                <?= $csrfField ?? '' ?>

                <div class="form-group">
                    <label for="username">Username *</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars($form['username'] ?? '') ?>"
                        required
                        minlength="3"
                        maxlength="20"
                        pattern="[a-zA-Z0-9_]{3,20}"
                        autocomplete="username"
                        placeholder="e.g., blessings_emulyn"
                        title="3–20 characters: letters, numbers, or underscores"
                    >
                    <small class="help-text">3–20 characters, letters, numbers, or underscores.</small>
                </div>

                <div class="form-group">
                    <label for="fullname">Full Name *</label>
                    <input
                        type="text"
                        id="fullname"
                        name="fullname"
                        value="<?= htmlspecialchars($form['fullname'] ?? '') ?>"
                        required
                        minlength="2"
                        maxlength="120"
                        autocomplete="name"
                        placeholder="e.g., Blessings Emulyn"
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($form['phone'] ?? '') ?>"
                        required
                        inputmode="tel"
                        pattern="^\+?[0-9][0-9\s\-]{8,18}$"
                        autocomplete="tel"
                        placeholder="e.g., +265 999 123 456"
                        title="Enter a valid phone number (9–15 digits)"
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email (optional)</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($form['email'] ?? '') ?>"
                        autocomplete="email"
                        placeholder="e.g., blessings@example.com"
                    >
                </div>

                <div class="form-group">
                    <label for="school">Current School *</label>
                    <input
                        type="text"
                        id="school"
                        name="school"
                        value="<?= htmlspecialchars($form['school'] ?? '') ?>"
                        required
                        minlength="2"
                        maxlength="200"
                        autocomplete="organization"
                        placeholder="e.g., Ntcheu Secondary School"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="5"
                        autocomplete="new-password"
                    >
                    <small class="help-text">Minimum 5 characters.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="5"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn">Sign Up</button>
            </form>

            <p>Already have an account? <a href="login.php">Login here</a></p>
        <?php endif; ?>
    </main>

    <script>
        document.querySelectorAll('#signupForm input[name="username"], #signupForm input[name="fullname"], #signupForm input[name="phone"], #signupForm input[name="email"], #signupForm input[name="school"]').forEach(function(input) {
            input.addEventListener('input', function() {
                sessionStorage.setItem('signup_' + this.name, this.value);
            });
        });

        window.addEventListener('load', function() {
            document.querySelectorAll('#signupForm input[name="username"], #signupForm input[name="fullname"], #signupForm input[name="phone"], #signupForm input[name="email"], #signupForm input[name="school"]').forEach(function(input) {
                const stored = sessionStorage.getItem('signup_' + input.name);
                if (stored && !input.value) {
                    input.value = stored;
                }
            });
        });

        <?php if (!empty($success)): ?>
            sessionStorage.clear();
        <?php endif; ?>
    </script>
</body>
</html>
