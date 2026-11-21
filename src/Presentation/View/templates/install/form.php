<?php
/**
 * @var array<string, string> $errors
 * @var array{full_name: string, phone: string} $old
 * @var bool $tokenConfigured
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $field): string => isset($errors[$field])
    ? '<span class="field__error">' . e($errors[$field]) . '</span>'
    : '';
?>
<div class="card card--narrow">
    <h1>Set up SMART Circle</h1>
    <p>This creates the database tables and your tutor account. It works only once.</p>

    <?php if (!$tokenConfigured): ?>
        <div class="alert alert--error" role="alert">
            Add an <code>install_token</code> (at least 16 characters) to <code>secrets.php</code> on the server first.
        </div>
    <?php endif; ?>

    <form method="post" action="/install" class="form" novalidate>
        <?= $csrf->field() ?>
        <label class="field">
            <span class="field__label">Install token (from secrets.php)</span>
            <input type="password" name="install_token" autocomplete="off" required>
            <?= $err('token') ?>
        </label>
        <label class="field">
            <span class="field__label">Your full name</span>
            <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" autocomplete="name" required>
            <?= $err('full_name') ?>
        </label>
        <label class="field">
            <span class="field__label">Your phone number (you'll log in with it)</span>
            <input type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="0999 123 456" required>
            <?= $err('phone') ?>
        </label>
        <label class="field">
            <span class="field__label">Password (at least 10 characters)</span>
            <input type="password" name="password" autocomplete="new-password" required>
            <?= $err('password') ?>
        </label>
        <label class="field">
            <span class="field__label">Repeat password</span>
            <input type="password" name="password_confirm" autocomplete="new-password" required>
            <?= $err('password_confirm') ?>
        </label>
        <button type="submit" class="btn btn--block">Set up SMART Circle</button>
    </form>
</div>
