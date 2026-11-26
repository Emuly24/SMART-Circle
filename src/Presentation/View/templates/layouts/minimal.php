<?php
/**
 * Minimal layout for setup pages (no navigation).
 * @var string $content
 * @var string $title
 * @var string $appName
 * @var callable $asset
 */
?>
<!doctype html>
<html lang="en" data-theme="light" data-theme-fixed>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? $appName) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= e($asset('brand/favicon.svg')) ?>" type="image/svg+xml">
    <script src="<?= e($asset('js/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body class="minimal">
<main id="main" class="minimal__main">
    <img class="minimal__logo" src="<?= e($asset('brand/logo-stacked.svg')) ?>" alt="<?= e($appName) ?>" width="200" height="155">
    <?= $content ?>
</main>
</body>
</html>
