<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Already Logged In - SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/progress_tracker.php'; ?>

    <main class="container">
        <section class="card">
            <h2>Welcome back, <?= htmlspecialchars($firstName ?? 'User') ?>!</h2>
            <p>We wish you a joyful and meaningful use of SMART Circle.</p>
            <div class="card-buttons" style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </section>
    </main>

    <?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/toc_navigator.php'; ?>
</body>
</html>
