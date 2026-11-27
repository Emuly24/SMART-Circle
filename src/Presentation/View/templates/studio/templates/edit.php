<?php
/**
 * @var array<string, mixed> $t
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var array{subject: string, body: string} $preview
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/templates">Message templates</a></p>
        <h1><?= e($t['name']) ?></h1>
        <p class="muted"><?= $t['custom'] ? 'Your wording' . (!empty($t['updated_at']) ? ', saved ' . e(date('j M Y', (int) strtotime((string) $t['updated_at']))) : '') : 'Default wording' ?></p>
        <form method="post" action="/studio/templates/<?= e($t['key']) ?>" class="form card"><?= $csrf->field() ?>
            <?php if ($t['subject'] !== null): ?>
                <label class="field"><span class="field__label">Subject / title</span><input type="text" name="subject" maxlength="200" value="<?= e($old['subject']) ?>" required><?= $err('subject') ?></label>
            <?php endif; ?>
            <label class="field"><span class="field__label">Message</span><textarea name="body" rows="10" maxlength="5000" required><?= e($old['body']) ?></textarea><?= $err('body') ?></label>
            <div class="template-help">
                <p class="field__label">Placeholders you can use</p>
                <ul><?php foreach ($t['placeholders'] as $p => $meaning): ?><li><code>{{<?= e($p) ?>}}</code> <?= e($meaning) ?><?= in_array($p, $t['required'], true) ? ' <strong>(must stay)</strong>' : '' ?></li><?php endforeach; ?></ul>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save my wording</button></div>
        </form>
        <?php if ($t['custom']): ?>
            <form method="post" action="/studio/templates/<?= e($t['key']) ?>/reset" class="inline-form js-confirm" data-confirm="Go back to the default wording?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Reset to default</button></form>
        <?php endif; ?>
        <h2>Preview</h2>
        <div class="card template-preview">
            <?php if ($t['subject'] !== null): ?><p><strong><?= e($preview['subject']) ?></strong></p><?php endif; ?>
            <p><?= nl2br(e($preview['body'])) ?></p>
            <p class="muted">Sample details are used for the preview.</p>
        </div>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
