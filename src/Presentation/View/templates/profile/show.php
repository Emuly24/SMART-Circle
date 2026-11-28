<?php
/**
 * @var array<string, mixed> $user
 * @var string|null $error
 * @var string|null $emailError
 * @var string|null $titleError
 */
$roles = ['tutor' => 'Admin', 'team' => 'SMART Circle Team', 'student' => 'Student', 'partner' => 'Partner teacher', 'alumni' => 'Alumni'];
?>
<section class="section section--tight">
    <div class="container narrow">
        <h1>My profile</h1>
        <div class="card profile-card">
            <?= $view->partial('avatar', ['id' => (int) $user['id'], 'name' => (string) $user['full_name'], 'at' => $user['avatar_at'] ?? null, 'size' => 'lg']) ?>
            <div>
                <h2 class="h3"><?= e($user['full_name']) ?></h2>
                <p class="muted"><?= e($roles[$user['role']] ?? $user['role']) ?> · <?= e(\SmartCircle\Support\PhoneNumber::display((string) $user['phone'])) ?></p>
            </div>
        </div>
        <form method="post" action="/profile/photo" enctype="multipart/form-data" class="form card js-photo-form">
            <?= $csrf->field() ?>
            <h2 class="h3"><?= empty($user['avatar_at']) ? 'Add a profile picture' : 'Change your picture' ?></h2>
            <label class="field"><span class="field__label">Photo <span class="muted">(JPG, PNG or WebP)</span></span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                <?php if ($error !== null): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
                <span class="field__hint">It is made square and small to save data. Choose a clear photo of your face; no group photos, please.</span></label>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save photo</button></div>
        </form>
        <?php if (!empty($user['avatar_at'])): ?>
            <form method="post" action="/profile/photo/remove" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove my picture</button></form>
        <?php endif; ?>
        <?php if ($user['role'] !== 'student'): ?>
            <form method="post" action="/profile/title" class="form card" id="title">
                <?= $csrf->field() ?>
                <h2 class="h3">How should we address you?</h2>
                <label class="field field--short"><span class="field__label">Title</span>
                    <select name="title">
                        <option value="">No title (use my name)</option>
                        <?php foreach (\SmartCircle\Support\NameTags::TITLES as $t): ?><option value="<?= e($t) ?>" <?= ($user['honorific'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (!empty($titleError)): ?><span class="field__error"><?= e($titleError) ?></span><?php endif; ?>
                    <span class="field__hint">Messages and emails can then greet you as, for example, “<?= e(\SmartCircle\Support\NameTags::values(['full_name' => (string) $user['full_name'], 'title' => $user['honorific'] ?? 'Mr'])['title_surname']) ?>”.</span></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
            </form>
        <?php endif; ?>
        <form method="post" action="/profile/email" class="form card" id="email">
            <?= $csrf->field() ?>
            <h2 class="h3">Email</h2>
            <label class="field"><span class="field__label">Email address <span class="muted">(optional)</span></span>
                <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" autocomplete="email" maxlength="190">
                <?php if (!empty($emailError)): ?><span class="field__error"><?= e($emailError) ?></span><?php endif; ?></label>
            <label class="choice choice--block"><input type="checkbox" name="email_notifications" value="1" <?= (int) ($user['email_notifications'] ?? 1) === 1 ? 'checked' : '' ?>> Also send my notifications to this email</label>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/photo.js')) ?>" defer></script>
