<?php
/**
 * @var string $action
 * @var string $draft
 * @var string|null $error
 * @var string $label
 */
?>
<form method="post" action="<?= e($action) ?>" class="form card reply-form">
    <?= $csrf->field() ?>
    <label class="field"><span class="field__label"><?= e($label) ?></span>
        <textarea name="body" rows="4" maxlength="4000" required><?= e($draft) ?></textarea>
        <?php if ($error !== null): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        <span class="field__hint">Messages are kept and cannot be deleted.</span></label>
    <div class="form-actions"><button type="submit" class="btn btn--small">Send</button></div>
</form>
