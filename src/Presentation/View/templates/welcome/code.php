<?php
/**
 * @var string $error
 * @var string $phone
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section">
    <div class="container narrow">
        <div class="card">
            <p class="eyebrow">Admitted students</p>
            <h1>Set up your account</h1>
            <p>Enter your phone number and the code your tutor gave you. (Forgot your password? Ask the tutor for a new code and start here.)</p>
            <?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="/welcome" class="form">
                <?= $csrf->field() ?>
                <label class="field">
                    <span class="field__label">Phone number</span>
                    <input type="tel" name="phone" value="<?= e($phone) ?>" inputmode="tel" autocomplete="tel" placeholder="0999 123 456" required>
                </label>
                <label class="field">
                    <span class="field__label">Setup code (8 letters and numbers)</span>
                    <input type="text" name="code" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" placeholder="ABCD 2345" required>
                </label>
                <button type="submit" class="btn btn--block">Continue</button>
            </form>
        </div>
    </div>
</section>
