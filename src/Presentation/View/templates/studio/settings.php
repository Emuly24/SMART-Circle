<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Settings</h1>
        <form method="post" action="/studio/settings" class="form card">
            <?= $csrf->field() ?>
            <label class="field"><span class="field__label">Your WhatsApp number</span>
                <input type="tel" name="tutor_whatsapp" value="<?= e($old['tutor_whatsapp'] ?? '') ?>" autocomplete="tel"><?= $err('tutor_whatsapp') ?>
                <span class="field__hint">Partner teachers use it to send you resources and syllabus documents on WhatsApp.</span></label>
            <label class="field"><span class="field__label">WhatsApp community invite link</span>
                <input type="url" name="whatsapp_community" value="<?= e($old['whatsapp_community'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..."><?= $err('whatsapp_community') ?>
                <span class="field__hint">Leave empty until the community exists. Once added, students, teachers, alumni and the team see a “Join” card.</span></label>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
        </form>
        <a class="card section-card community-card" href="/studio/templates"><span><strong>Message templates</strong><br><span class="muted">Change the wording of guardian messages, decision emails and the nudge to quiet students.</span></span></a>
        <a class="card section-card community-card" href="/studio/mail"><span><strong>Email</strong><br><span class="muted">See emails waiting and sent, send them now, or send yourself a test.</span></span></a>
        <?php $assets = $siteAssets(); ?>
        <div class="card section-card" id="signature">
            <h2 class="h3">Your signature</h2>
            <p>Printed on every letter and certificate (admission letters, pledge certificates, term reports, partner invitations). A photo of your signature on white paper works best: the background is removed for you.</p>
            <?php if ($assets['signature'] !== null): ?><img class="settings-signature" src="/site/signature?v=<?= e($assets['signature']) ?>" alt="Your signature"><?php endif; ?>
            <form method="post" action="/studio/settings/signature/upload" enctype="multipart/form-data" class="form"><?= $csrf->field() ?>
                <label class="field"><span class="field__label"><?= $assets['signature'] === null ? 'Upload your signature' : 'Replace it' ?></span><input type="file" name="picture" accept="image/png,image/jpeg,image/webp" required></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save signature</button></div></form>
            <?php if ($assets['signature'] !== null): ?><form method="post" action="/studio/settings/signature/remove" class="inline-form js-confirm" data-confirm="Remove your signature from letters?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove</button></form><?php endif; ?>
        </div>
        <div class="card section-card" id="photo">
            <h2 class="h3">Your photo</h2>
            <p>Shown with your story on the About page.</p>
            <?php if ($assets['photo'] !== null): ?><img class="story__photo story__photo--img" src="/site/photo?v=<?= e($assets['photo']) ?>" alt="Your photo" width="140" height="140"><?php endif; ?>
            <form method="post" action="/studio/settings/photo/upload" enctype="multipart/form-data" class="form"><?= $csrf->field() ?>
                <label class="field"><span class="field__label"><?= $assets['photo'] === null ? 'Upload your photo' : 'Replace it' ?></span><input type="file" name="picture" accept="image/png,image/jpeg,image/webp" required></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save photo</button></div></form>
            <?php if ($assets['photo'] !== null): ?><form method="post" action="/studio/settings/photo/remove" class="inline-form js-confirm" data-confirm="Remove your photo from the About page?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove</button></form><?php endif; ?>
        </div>
        <div class="card section-card">
            <h2 class="h3">Back up the database</h2>
            <p>Download a full copy of the database to keep safe (free hosting can lose data). It contains students’ personal details: store it privately, for example in your own Google Drive, never in a shared folder.</p>
            <form method="post" action="/studio/backup" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small">Download a backup</button></form>
        </div>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
