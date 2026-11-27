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
        <div class="card section-card">
            <h2 class="h3">Back up the database</h2>
            <p>Download a full copy of the database to keep safe (free hosting can lose data). It contains students’ personal details: store it privately, for example in your own Google Drive, never in a shared folder.</p>
            <form method="post" action="/studio/backup" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small">Download a backup</button></form>
        </div>
    </div>
</section>
