<?php
/**
 * Main layout.
 * @var string $content
 * @var string $title
 * @var string $appName
 * @var string $tagline
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var callable $asset
 * @var callable $flashes
 * @var string $path
 */
$user = $ctx->user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $appName) ?> · <?= e($appName) ?></title>
    <meta name="description" content="Free science, mathematics and English grammar support for Form 1–4 students in Malawi.">
    <meta name="theme-color" content="#1D4E89">
    <link rel="icon" href="<?= e($asset('brand/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= e($asset('brand/favicon-32.png')) ?>" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="<?= e($asset('brand/apple-touch-icon-180.png')) ?>">
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="<?= e($appName) ?> home">
            <img src="<?= e($asset('brand/logo-horizontal.svg')) ?>" alt="<?= e($appName) ?>" width="220" height="56">
        </a>
        <nav class="site-nav" aria-label="Main">
            <a href="/" <?= ($path ?? '') === '/' ? 'aria-current="page"' : '' ?>>Home</a>
            <a href="/about" <?= ($path ?? '') === '/about' ? 'aria-current="page"' : '' ?>>About</a>
            <?php if ($user === null): ?>
                <a class="btn btn--small" href="/login">Log in</a>
            <?php else: ?>
                <a href="/dashboard">Dashboard</a>
                <form method="post" action="/logout" class="inline-form">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small btn--ghost">Log out</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="site-main">
    <?php foreach ($flashes() as $flash): ?>
        <div class="container">
            <div class="alert alert--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <img src="<?= e($asset('brand/logo-horizontal-reversed.svg')) ?>" alt="" width="180" height="46">
        <p><?= e($tagline) ?> Free support for Form 1–4 students in Malawi.</p>
        <p class="site-footer__small">&copy; <?= e(date('Y')) ?> <?= e($appName) ?></p>
    </div>
</footer>
</body>
</html>
