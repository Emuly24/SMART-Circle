<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Already Applied - SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
    <?php include dirname(__DIR__, 2) . '/includes/progress_tracker.php'; ?>

    <main class="container">
        <section class="card">
            <h2>You have already submitted an application</h2>
            <p>Your application is currently under review. Please wait for the admin to respond.</p>
            <p>If this is your friend using your phone, please log out and let them create their own account.</p>
            <div class="card-buttons">
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </section>
    </main>
</body>
</html>
