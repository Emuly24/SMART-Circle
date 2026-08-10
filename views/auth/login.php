<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Login - SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

    <main class="login-container">
        <section aria-labelledby="login-heading">
            <h2 id="login-heading" class="login-title">Welcome Back</h2>

            <?php if (!empty($error)): ?>
                <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <div class="form-group">
                    <label for="login">Phone Number or Email</label>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        value="<?= htmlspecialchars($login ?? '') ?>"
                        required
                        autocomplete="username"
                        inputmode="email"
                        pattern="^(\+?[0-9][0-9\s\-]{8,14}|[^\s@]+@[^\s@]+\.[^\s@]+)$"
                        title="Enter a valid phone number (9–15 digits) or email address"
                        placeholder="e.g., +265 999 123 456 or you@example.com"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        minlength="5"
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >
                </div>

                <button type="submit" class="btn btn-login">Login</button>
            </form>

            <nav class="login-links" aria-label="Account links">
                <a href="signup.php">Don&rsquo;t have an account? Sign up here</a>
                <a href="forgot_password.php">Forgot password?</a>
            </nav>
        </section>
    </main>

    <footer class="footer">
        <a href="index.php" class="btn-back">&larr; Back</a>
    </footer>
</body>
</html>
