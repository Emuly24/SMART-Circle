<?php
/**
 * @var string $error
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section">
    <div class="container narrow">
        <div class="card">
            <h1>Choose a password</h1>
            <p>At least 8 characters. Don't share it, even with friends in the class.</p>
            <?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="/welcome/password" class="form">
                <?= $csrf->field() ?>
                <label class="field">
                    <span class="field__label">New password</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                </label>
                <label class="field">
                    <span class="field__label">Type it again</span>
                    <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
                </label>
                <button type="submit" class="btn btn--block">Save password</button>
            </form>
        </div>
    </div>
</section>
