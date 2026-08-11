<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Error - SMART Circle') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>

    <main class="container">
        <div class="card error" role="alert">
            <h1>Something went wrong</h1>
            <p><?= htmlspecialchars($message ?? 'Please try again later.') ?></p>
            <a href="index.php" class="btn">Return Home</a>
        </div>
    </main>

    <?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
</body>
</html>
