<?php
/**
 * @var list<array<string, mixed>> $posts
 * @var array<string, string> $kinds
 * @var bool $canWrite
 * @var bool $isTutor
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1><?= $canWrite ? 'Alumni community' : 'From our alumni' ?></h1>
        <p class="lead"><?= $canWrite ? 'Ask questions, share opportunities and tell your story. Be kind; posts are kept and the tutor moderates.' : 'Stories and opportunities from SMART Circle students who went before you.' ?></p>
        <?php if (!$canWrite && $errors !== []): ?><div class="alert alert--error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <?php if ($posts === []): ?><div class="card"><p class="muted">Nothing here yet.</p></div><?php else: ?>
            <ul class="inbox">
                <?php foreach ($posts as $p): ?>
                    <li class="inbox-row"><a href="/alumni/community/<?= e($p['id']) ?>">
                        <span class="inbox-row__top"><span class="inbox-row__subject"><span class="chip chip--planned"><?= e($kinds[$p['kind']] ?? $p['kind']) ?></span> <?= e($p['title']) ?></span>
                            <span class="muted inbox-row__when"><?= e(date('j M', (int) strtotime((string) $p['last_reply_at']))) ?></span></span>
                        <span class="inbox-row__who muted"><?= e($p['full_name'] ?? 'Former member') ?><?= $p['left_year'] !== null ? ' · class of ' . e($p['left_year']) : ($p['role'] === 'tutor' ? ' · tutor' : '') ?> · <?= e($p['replies']) ?> repl<?= (int) $p['replies'] === 1 ? 'y' : 'ies' ?>
                            <?php if ($isTutor && $p['status'] !== 'visible'): ?> · <strong>hidden</strong><?php endif; ?><?php if ($isTutor && (bool) $p['for_students']): ?> · shared with students<?php endif; ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canWrite): ?>
            <form method="post" action="/alumni/community" class="form card">
                <?= $csrf->field() ?>
                <h2 class="h3">New post</h2>
                <div class="field-row">
                    <label class="field"><span class="field__label">Kind</span><select name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('kind') ?></label>
                    <label class="field"><span class="field__label">Title</span><input type="text" name="title" maxlength="150" required value="<?= e($old['title'] ?? '') ?>"><?= $err('title') ?></label>
                </div>
                <label class="field"><span class="field__label">Post</span><textarea name="body" rows="5" maxlength="4000" required><?= e($old['body'] ?? '') ?></textarea><?= $err('body') ?></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Post</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
