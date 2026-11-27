<?php
/**
 * @var array<string, mixed> $p
 * @var array<string, string> $kinds
 * @var bool $canWrite
 * @var bool $isTutor
 * @var string $draft
 * @var string|null $error
 */
$post = $p['post'];
$who = static fn (array $r): string => ($r['full_name'] ?? 'Former member') . ($r['left_year'] !== null ? ' · class of ' . $r['left_year'] : (($r['role'] ?? '') === 'tutor' ? ' · tutor' : ''));
$mod = static fn (string $what, int $id, string $action, string $label) => '<form method="post" action="/alumni/community/' . e($id) . '/moderate" class="inline-form">'
    . $csrf->field() . '<input type="hidden" name="what" value="' . e($what) . '"><input type="hidden" name="action" value="' . e($action) . '">'
    . '<button type="submit" class="btn btn--small btn--ghost">' . e($label) . '</button></form>';
$last = count($p['replies']) - 1;
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/alumni/community"><?= $canWrite ? 'Alumni community' : 'From our alumni' ?></a></p>
        <h1><?= e($post['title']) ?></h1>
        <p class="muted"><span class="chip chip--planned"><?= e($kinds[$post['kind']] ?? $post['kind']) ?></span> <?= e($who($post)) ?> · <?= e(date('j M Y', (int) strtotime((string) $post['created_at']))) ?>
            <?php if ($post['status'] !== 'visible'): ?> · <strong>hidden from alumni</strong><?php endif; ?></p>
        <div class="card msg__body"><?= nl2br(e($post['body'])) ?></div>
        <?php if ($isTutor): ?>
            <div class="contact-buttons">
                <?= $post['status'] === 'visible' ? $mod('post', (int) $post['id'], 'hide', 'Hide post') : $mod('post', (int) $post['id'], 'show', 'Show post') ?>
                <?= (bool) $post['for_students'] ? $mod('post', (int) $post['id'], 'unshare', 'Stop sharing with Form 3–4') : $mod('post', (int) $post['id'], 'share', 'Share with Form 3–4 students') ?>
            </div>
        <?php endif; ?>
        <ol class="thread">
            <?php foreach ($p['replies'] as $i => $r): ?>
                <li class="msg"<?= $i === $last ? ' id="latest"' : '' ?>>
                    <p class="msg__meta"><strong><?= e($who($r)) ?></strong> <span class="muted">· <?= e(date('j M Y, H:i', (int) strtotime((string) $r['created_at']))) ?></span><?= $r['status'] !== 'visible' ? ' · <strong>hidden</strong>' : '' ?></p>
                    <div class="msg__body"><?= nl2br(e($r['body'])) ?></div>
                    <?php if ($isTutor): ?><form method="post" action="/alumni/community/<?= e($r['id']) ?>/moderate" class="inline-form"><?= $csrf->field() ?><input type="hidden" name="what" value="reply"><input type="hidden" name="action" value="<?= $r['status'] === 'visible' ? 'hide' : 'show' ?>"><button type="submit" class="btn btn--small btn--ghost"><?= $r['status'] === 'visible' ? 'Hide reply' : 'Show reply' ?></button></form><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($canWrite && $post['status'] === 'visible'): ?>
            <?= $view->partial('reply_form', ['action' => '/alumni/community/' . $post['id'] . '/reply', 'draft' => $draft, 'error' => $error, 'label' => 'Reply']) ?>
        <?php endif; ?>
    </div>
</section>
