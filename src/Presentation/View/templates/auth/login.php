<?php
/**
 * @var string $phone
 * @var string $error
 * @var bool $installed
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section">
    <div class="container narrow">
        <div class="card">
            <h1>Log in</h1>
            <?php if (!empty($installed)): ?>
                <div class="alert alert--success" role="status">
                    SMART Circle is set up. Log in with the tutor account you just created.
                    You can now remove <code>install_token</code> from secrets.php.
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert--error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="/login" class="form" novalidate>
                <?= $csrf->field() ?>
                <label class="field">
                    <span class="field__label">Phone number</span>
                    <input type="tel" name="phone" value="<?= e($phone) ?>" autocomplete="tel" inputmode="tel"
                           placeholder="0999 123 456" required>
                </label>
                <label class="field">
                    <span class="field__label">Password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn btn--block">Log in</button>
            </form>
            <p class="note">Forgot your password? Ask the tutor for a reset code.</p>
        </div>
    </div>
</section>
