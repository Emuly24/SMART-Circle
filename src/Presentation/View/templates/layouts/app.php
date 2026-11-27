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
 * @var callable|null $messageCount
 * @var callable|null $community
 */
$user = $ctx->user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $appName) ?> · <?= e($appName) ?></title>
    <?php
    $metaDescription = $description ?? 'Free recorded lessons, notes and handouts in Mathematics, Physics, Chemistry, Biology and English grammar for Form 1–4 students in Malawi. Every lesson shows why it matters in real life.';
    $metaTitle = isset($title) && $title !== $appName ? $title . ' · ' . $appName : $appName . ': ' . ($tagline ?? '');
    $base = $siteUrl ?? '';
    ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($appName) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:locale" content="en_GB">
    <?php if ($base !== ''): ?>
    <meta property="og:url" content="<?= e($base . ($path ?? '/')) ?>">
    <meta property="og:image" content="<?= e($base) ?>/assets/brand/share-card.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="SMART Circle: free lessons that show why it matters">
    <link rel="canonical" href="<?= e($base . ($path ?? '/')) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#1D4E89">
    <link rel="icon" href="<?= e($asset('brand/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= e($asset('brand/favicon-32.png')) ?>" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="<?= e($asset('brand/apple-touch-icon-180.png')) ?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <script src="<?= e($asset('js/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body data-role="<?= e($user['role'] ?? 'guest') ?>" data-path="<?= e($path ?? '/') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/" aria-label="<?= e($appName) ?> home">
            <img class="brand__logo brand__logo--light" src="<?= e($asset('brand/logo-horizontal.svg')) ?>" alt="<?= e($appName) ?>" width="220" height="56">
            <img class="brand__logo brand__logo--dark" src="<?= e($asset('brand/logo-horizontal-dark.svg')) ?>" alt="" width="220" height="56">
        </a>
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav">
            <span class="nav-toggle__bars" aria-hidden="true"></span><span class="nav-toggle__label">Menu</span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Main">
            <?php if ($user === null): ?>
                <a href="/" <?= ($path ?? '') === '/' ? 'aria-current="page"' : '' ?>>Home</a>
                <a href="/library" <?= str_starts_with($path ?? '', '/library') ? 'aria-current="page"' : '' ?>>Library</a>
                <a href="/about" <?= ($path ?? '') === '/about' ? 'aria-current="page"' : '' ?>>About</a>
                <a class="btn btn--small" href="/login">Log in</a>
            <?php else: $role = (string) $user['role']; ?>
                <a href="/" <?= ($path ?? '') === '/' ? 'aria-current="page"' : '' ?>>Home</a>
                <a href="/dashboard" <?= ($path ?? '') === '/dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <?php if ($role === 'tutor'): ?>
                    <a href="/studio/students" <?= str_starts_with($path ?? '', '/studio/students') ? 'aria-current="page"' : '' ?>>Students</a>
                    <a href="/studio/marking" <?= str_starts_with($path ?? '', '/studio/marking') ? 'aria-current="page"' : '' ?>>Marking</a>
                    <a href="/studio/community" <?= str_starts_with($path ?? '', '/studio/community') ? 'aria-current="page"' : '' ?>>Community</a>
                    <a href="/studio/practice" <?= str_starts_with($path ?? '', '/studio/practice') ? 'aria-current="page"' : '' ?>>Try as</a>
                <?php elseif ($role === 'team'): ?>
                    <a href="/studio/lessons" <?= str_starts_with($path ?? '', '/studio/lessons') ? 'aria-current="page"' : '' ?>>My lessons</a>
                    <a href="/studio/notes" <?= str_starts_with($path ?? '', '/studio/notes') ? 'aria-current="page"' : '' ?>>Notes</a>
                    <a href="/studio/marking" <?= str_starts_with($path ?? '', '/studio/marking') ? 'aria-current="page"' : '' ?>>Marking</a>
                <?php elseif ($role === 'partner'): ?>
                    <a href="/partner/coverage" <?= str_starts_with($path ?? '', '/partner/coverage') ? 'aria-current="page"' : '' ?>>Topics taught</a>
                    <a href="/partner/textbooks" <?= str_starts_with($path ?? '', '/partner/textbooks') ? 'aria-current="page"' : '' ?>>Textbooks</a>
                    <a href="/partner/calendar" <?= str_starts_with($path ?? '', '/partner/calendar') ? 'aria-current="page"' : '' ?>>Calendar</a>
                    <a href="/partner/reviews" <?= str_starts_with($path ?? '', '/partner/reviews') ? 'aria-current="page"' : '' ?>>Check notes</a>
                    <a href="/partner/questions" <?= str_starts_with($path ?? '', '/partner/questions') ? 'aria-current="page"' : '' ?>>Suggest questions</a>
                    <?php if ($ctx->isGuestTeacher()): ?><a href="/studio/lessons" <?= str_starts_with($path ?? '', '/studio/lessons') ? 'aria-current="page"' : '' ?>>My lessons</a><?php endif; ?>
                    <a href="/partner/contribute" <?= str_starts_with($path ?? '', '/partner/contribute') ? 'aria-current="page"' : '' ?>>Contribute</a>
                <?php elseif ($role === 'alumni'): ?>
                    <a href="/alumni/community" <?= str_starts_with($path ?? '', '/alumni/community') ? 'aria-current="page"' : '' ?>>Community</a>
                    <a href="/results/mine" <?= str_starts_with($path ?? '', '/results') ? 'aria-current="page"' : '' ?>>Results</a>
                    <a href="/office-hours" <?= str_starts_with($path ?? '', '/office-hours') ? 'aria-current="page"' : '' ?>>Office hours</a>
                <?php else: ?>
                    <a href="/topics" <?= str_starts_with($path ?? '', '/topics') ? 'aria-current="page"' : '' ?>>Topics</a>
                    <a href="/notes" <?= str_starts_with($path ?? '', '/notes') ? 'aria-current="page"' : '' ?>>Notes</a>
                    <a href="/assessments" <?= str_starts_with($path ?? '', '/assessments') ? 'aria-current="page"' : '' ?>>Exams</a>
                    <a href="/community" <?= str_starts_with($path ?? '', '/community') ? 'aria-current="page"' : '' ?>>Community</a>
                <?php endif; ?>
                <?php $msgs = isset($messageCount) ? $messageCount() : 0; ?>
                <a href="/messages" <?= str_starts_with($path ?? '', '/messages') ? 'aria-current="page"' : '' ?>>Messages<?php if ($msgs > 0): ?> <span class="nav-count" aria-label="<?= e($msgs) ?> unread"><?= e($msgs > 99 ? '99+' : $msgs) ?></span><?php endif; ?></a>
                <?php $unread = isset($unreadCount) ? $unreadCount() : 0; ?>
                <a href="/notifications" class="bell" aria-label="Notifications<?= $unread > 0 ? ', ' . e($unread) . ' unread' : '' ?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
                    <?php if ($unread > 0): ?><span class="bell__count"><?= e($unread > 99 ? '99+' : $unread) ?></span><?php endif; ?>
                </a>
                <a href="/profile" class="nav-avatar" aria-label="My profile"><?= $view->partial('avatar', ['id' => (int) $user['id'], 'name' => (string) $user['full_name'], 'at' => $user['avatar_at'] ?? null, 'size' => 'sm']) ?></a>
                <form method="post" action="/logout" class="inline-form">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small btn--ghost">Log out</button>
                </form>
            <?php endif; ?>
            <button type="button" class="tour-start" hidden aria-label="Show me around"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 17a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm1.4-5.6V15h-2.6v-2.4c0-1.1.7-1.7 1.4-2.2.6-.4 1-.7 1-1.3a1.2 1.2 0 0 0-2.4 0H8.2a3.8 3.8 0 0 1 7.6 0c0 1.7-1.1 2.5-1.9 3-.3.2-.5.3-.5.3Z"/></svg><span class="tour-start__label">Tour</span></button>
            <details class="theme-menu">
                <summary aria-label="Display theme"><svg class="theme-menu__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3a9 9 0 0 1 0 18Z" fill="currentColor"/></svg><span class="theme-menu__label">Theme</span></summary>
                <div class="theme-menu__panel" role="group" aria-label="Display theme">
                    <button type="button" data-theme-choice="light" aria-pressed="false"><span aria-hidden="true">☀</span> Light</button>
                    <button type="button" data-theme-choice="comfort" aria-pressed="false"><span aria-hidden="true">📖</span> Eye comfort</button>
                    <button type="button" data-theme-choice="dark" aria-pressed="false"><span aria-hidden="true">🌙</span> Dark</button>
                    <button type="button" data-theme-choice="auto" aria-pressed="false"><span aria-hidden="true">⚙</span> Same as my device</button>
                    <p class="theme-menu__hint">Eye comfort uses warm, softer colours for long reading.</p>
                </div>
            </details>
        </nav>
    </div>
</header>

<?php if ($ctx->practiceAdmin() !== null): ?>
    <div class="practice-bar" role="status">
        <div class="container practice-bar__inner">
            <span><strong>Practice mode:</strong> you are seeing SMART Circle as <?= e($user['full_name'] ?? '') ?>. Anything you do here is practice data.</span>
            <form method="post" action="/practice/leave" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small">Back to admin</button></form>
        </div>
    </div>
<?php endif; ?>
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
        <img src="<?= e($asset('brand/logo-horizontal-dark.svg')) ?>" alt="" width="180" height="46">
        <p><?= e($tagline) ?> Free support for Form 1–4 students in Malawi.</p>
        <p class="site-footer__links"><a href="/about">About &amp; our story</a> · <a href="/library">Free library</a> · <a href="/results">MANEB results</a> · <a href="/safeguarding">Safeguarding &amp; privacy</a> · <a href="/verify">Verify a document</a></p>
        <p class="site-footer__small">&copy; <?= e(date('Y')) ?> <?= e($appName) ?> · Free, always. No favours of any kind.</p>
    </div>
</footer>
<script src="<?= e($asset('js/site.js')) ?>" defer></script>
<script src="<?= e($asset('js/tour.js')) ?>" defer></script>
<script src="<?= e($asset('js/share.js')) ?>" defer></script>
</body>
</html>
