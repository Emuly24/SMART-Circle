<?php
/**
 * @var array<string, mixed> $k g, lang, admission, monthly, call, links, stats
 * @var array<string, string> $languages
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$g = $k['g'];
$l = $k['links'];
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$v = static fn (string $f): string => (string) (array_key_exists($f, $old) ? $old[$f] : ($g[$f] ?? ''));
$sent = static fn (string $what, string $channel, string $label) => '<form method="post" action="/studio/guardians/' . e($g['user_id']) . '/sent" class="inline-form">'
    . $csrf->field() . '<input type="hidden" name="what" value="' . e($what) . '"><input type="hidden" name="channel" value="' . e($channel) . '">'
    . '<button type="submit" class="btn btn--small btn--ghost">' . e($label) . '</button></form>';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/guardians">Guardians</a> · <a href="/studio/students/<?= e($g['user_id']) ?>"><?= e($g['full_name']) ?></a></p>
        <h1><?= e($g['guardian_name'] ?? 'Guardian') ?></h1>
        <p class="muted">Guardian of <?= e($g['full_name']) ?> (Form <?= e($g['form']) ?>)<?= $g['guardian_relationship'] ? ' · ' . e($g['guardian_relationship']) : '' ?> · writes in <strong><?= e($languages[$k['lang']]) ?></strong></p>
        <?php if ($g['status'] === 'confirmed'): ?>
            <div class="alert alert--success">Confirmed<?= $g['guardian_confirmed_via'] ? ' (' . e(['link' => 'from the link', 'call' => 'on a call', 'onboarding' => 'by the student at sign-up'][$g['guardian_confirmed_via']] ?? $g['guardian_confirmed_via']) . ')' : '' ?> on <?= e(date('j M Y', (int) strtotime((string) $g['guardian_confirmed_at']))) ?>.</div>
        <?php elseif ($g['status'] === 'declined'): ?>
            <div class="alert alert--error" role="alert">This person said they do not know <?= e($g['full_name']) ?>. Please check the guardian’s details with the student, then correct them below.</div>
        <?php else: ?>
            <div class="alert alert--info">Not yet confirmed. Send the message below; the guardian confirms with the link inside it.</div>
        <?php endif; ?>

        <div class="card section-card">
            <h2 class="h3">Welcome message</h2>
            <blockquote class="quote quote--plain"><p><?= nl2br(e($k['admission'])) ?></p></blockquote>
            <div class="contact-buttons">
                <?php if ($l['whatsapp']): ?><a class="btn btn--small btn--green" href="<?= e($l['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <?php if ($l['sms']): ?><a class="btn btn--small btn--ghost" href="<?= e($l['sms']) ?>">SMS</a><?php endif; ?>
                <?php if ($l['email']): ?><a class="btn btn--small btn--ghost" href="<?= e($l['email']) ?>">Email</a><?php endif; ?>
                <?php if ($l['tel']): ?><a class="btn btn--small btn--ghost" href="<?= e($l['tel']) ?>">Call</a><?php endif; ?>
            </div>
            <p class="contact-buttons"><span class="muted">After sending:</span> <?= $sent('admission', 'whatsapp', 'I sent it on WhatsApp') ?> <?= $sent('admission', 'phone', 'I sent it by SMS') ?><?= $l['email'] ? ' ' . $sent('admission', 'email', 'I sent the email') : '' ?></p>
            <details><summary>Points for a phone call</summary><ul><?php foreach ($k['call'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul></details>
            <?php if ($g['status'] !== 'confirmed'): ?>
                <form method="post" action="/studio/guardians/<?= e($g['user_id']) ?>/confirm" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small">They confirmed on a call</button></form>
            <?php endif; ?>
        </div>

        <div class="card section-card">
            <h2 class="h3">Monthly update for <?= e(date('F')) ?></h2>
            <p class="muted">This month so far: <?= e($k['stats']['lessons']) ?> lessons watched, <?= e($k['stats']['work']) ?> pieces of work handed in.</p>
            <blockquote class="quote quote--plain"><p><?= nl2br(e($k['monthly'])) ?></p></blockquote>
            <div class="contact-buttons">
                <?php if ($l['monthly_whatsapp']): ?><a class="btn btn--small btn--green" href="<?= e($l['monthly_whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <?php if ($l['monthly_sms']): ?><a class="btn btn--small btn--ghost" href="<?= e($l['monthly_sms']) ?>">SMS</a><?php endif; ?>
                <?= $sent('monthly', 'whatsapp', 'I sent this month’s update') ?>
            </div>
        </div>

        <form method="post" action="/studio/guardians/<?= e($g['user_id']) ?>" class="form card">
            <?= $csrf->field() ?>
            <h2 class="h3">Guardian details</h2>
            <div class="field-row">
                <label class="field"><span class="field__label">Name</span><input type="text" name="guardian_name" value="<?= e($v('guardian_name')) ?>" required><?= $err('guardian_name') ?></label>
                <label class="field"><span class="field__label">Relationship</span><input type="text" name="guardian_relationship" value="<?= e($v('guardian_relationship')) ?>" maxlength="40"></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">Phone for calls</span><input type="tel" name="guardian_phone" value="<?= e($v('guardian_phone')) ?>" required data-contacts="tel" data-contacts-name="guardian_name"><?= $err('guardian_phone') ?></label>
                <label class="field"><span class="field__label">WhatsApp <span class="muted">(if different)</span></span><input type="tel" name="guardian_whatsapp" value="<?= e($v('guardian_whatsapp')) ?>" data-contacts="tel"><?= $err('guardian_whatsapp') ?></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">Email</span><input type="email" name="guardian_email" value="<?= e($v('guardian_email')) ?>" data-contacts="email"><?= $err('guardian_email') ?></label>
                <label class="field"><span class="field__label">Language</span><select name="guardian_language"><?php foreach ($languages as $code => $lang): ?><option value="<?= e($code) ?>" <?= $v('guardian_language') === $code ? 'selected' : '' ?>><?= e($lang) ?></option><?php endforeach; ?></select><?= $err('guardian_language') ?></label>
            </div>
            <p class="field__hint">Changing the call number asks the guardian to confirm again with a new link.</p>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save details</button></div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/contacts.js')) ?>" defer></script>
