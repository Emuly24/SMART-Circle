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
 * @var callable|null $unreadCount
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
                <a href="/dashboard" <?= ($path ?? '') === '/dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <?php if ($user['role'] === 'partner'): ?>
                    <a href="/partner/coverage" <?= str_starts_with($path ?? '', '/partner/coverage') ? 'aria-current="page"' : '' ?>>Topics taught</a>
                    <a href="/partner/textbooks" <?= str_starts_with($path ?? '', '/partner/textbooks') ? 'aria-current="page"' : '' ?>>Textbooks</a>
                <?php else: ?>
                    <a href="/topics" <?= str_starts_with($path ?? '', '/topics') ? 'aria-current="page"' : '' ?>>Topics</a>
                    <?php if ($user['role'] === 'student'): ?>
                        <a href="/assessments" <?= str_starts_with($path ?? '', '/assessments') ? 'aria-current="page"' : '' ?>>Exams</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php $unread = isset($unreadCount) ? $unreadCount() : 0; ?>
                <a href="/notifications" class="bell" aria-label="Notifications<?= $unread > 0 ? ', ' . e($unread) . ' unread' : '' ?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
                    <?php if ($unread > 0): ?><span class="bell__count"><?= e($unread > 99 ? '99+' : $unread) ?></span><?php endif; ?>
                </a>
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
