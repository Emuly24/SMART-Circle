<?php
/**
 * @var string $action
 * @var string $draft
 * @var string|null $error
 * @var string $label
 * @var int|null $quoteId a message to quote (from ?quote=), found in $messages
 * @var list<array<string, mixed>>|null $messages
 * @var bool|null $nameTags show the one-click name tags (tutor and team)
 */
$quoted = null;
foreach (($messages ?? []) as $m) {
    if ((int) $m['id'] === (int) ($quoteId ?? 0)) {
        $quoted = $m;
    }
}
?>
<form method="post" action="<?= e($action) ?>" class="form card reply-form" id="reply">
    <?= $csrf->field() ?>
    <input type="hidden" name="quote_id" value="<?= e($quoted['id'] ?? '') ?>" class="js-quote-id">
    <div class="reply-quote<?= $quoted === null ? ' is-empty' : '' ?>" aria-live="polite">
        <blockquote class="msg__quote js-quote-text"><?php if ($quoted !== null): ?><span class="msg__quote-who"><?= e($quoted['full_name'] ?? 'Former member') ?> wrote:</span> <?= e(mb_strimwidth((string) $quoted['body'], 0, 220, '…')) ?><?php endif; ?></blockquote>
        <a href="?" class="btn btn--small btn--ghost js-quote-clear">Remove quote</a>
    </div>
    <?php if (!empty($nameTags)): ?><?= $view->partial('name_tags') ?><?php endif; ?>
    <label class="field"><span class="field__label"><?= e($label) ?></span>
        <textarea name="body" rows="4" maxlength="4000" required><?= e($draft) ?></textarea>
        <?php if ($error !== null): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        <span class="field__hint">Messages are kept and cannot be deleted.</span></label>
    <div class="form-actions"><button type="submit" class="btn btn--small">Send</button></div>
</form>
<script src="<?= e($asset('js/quote.js')) ?>" defer></script>
<?php if (!empty($nameTags)): ?><script src="<?= e($asset('js/name-tags.js')) ?>" defer></script><?php endif; ?>
