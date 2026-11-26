<?php
/**
 * Full-screen layout for the recording studio (no site footer; maximum board space).
 * @var string $content
 * @var string $title
 * @var string $appName
 * @var callable $asset
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<!doctype html>
<html lang="en" data-theme="light" data-theme-fixed>
<head>
    <meta charset="utf-8">
    <!-- No pinch-zoom on the board page: two fingers are for the board, not the browser. -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
    <meta name="theme-color" content="#1D4E89">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= e($title ?? 'Studio') ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= e($asset('brand/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e($asset('brand/apple-touch-icon-180.png')) ?>">
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e($asset('css/board.css')) ?>">
</head>
<body class="studio">
<?= $content ?>
</body>
</html>
