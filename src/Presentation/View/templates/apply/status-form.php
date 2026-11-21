<?php
/**
 * @var string $error
 * @var string $reference
 * @var string $phone
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section">
    <div class="container narrow">
        <div class="card">
            <h1>Check my application</h1>
            <?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="/apply/status" class="form">
                <?= $csrf->field() ?>
                <label class="field">
                    <span class="field__label">Reference number</span>
                    <input type="text" name="reference" value="<?= e($reference) ?>" placeholder="SC-27-7KQ4M" autocapitalize="characters" required>
                </label>
                <label class="field">
                    <span class="field__label">Phone number on the application</span>
                    <input type="tel" name="phone" value="<?= e($phone) ?>" inputmode="tel" placeholder="0999 123 456" required>
                </label>
                <button type="submit" class="btn btn--block">Check</button>
            </form>
        </div>
    </div>
</section>
