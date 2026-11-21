<?php
/**
 * A4 print layout for letters and certificates (Print or "Save as PDF" from the browser).
 * @var string $content
 * @var string $title
 * @var string $appName
 * @var callable $asset
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= e($asset('brand/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e($asset('css/print.css')) ?>">
</head>
<body class="print-body">
<div class="print-toolbar no-print">
    <button type="button" class="btn btn--small" id="print-btn">Print or save as PDF</button>
</div>
<?= $content ?>
<script src="<?= e($asset('js/print.js')) ?>" defer></script>
</body>
</html>
