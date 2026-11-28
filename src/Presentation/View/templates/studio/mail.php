<?php
/**
 * @var bool $enabled
 * @var array<string, int> $counts
 * @var list<array<string, mixed>> $recent
 * @var array<string, mixed>|null $job
 * @var array<string, mixed>|null $digest
 * @var array<string, string> $groups
 * @var array<string, int> $audience
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$labels = ['queued' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed'];
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$when = static fn (?string $t): string => $t ? date('j M, H:i', (int) strtotime($t)) : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/settings">Settings</a></p>
        <h1>Email</h1>
        <p class="lead">Emails go out through Zoho (<?= e($contactEmail ?? '') ?>) a few at a time after people visit the site, because free hosting has no timer. Replies arrive in your Zoho inbox.</p>
        <?php if (!$enabled): ?>
            <div class="alert alert--error"><strong>Email is switched off.</strong> Put your Zoho password (or a Zoho app password) in <code>secrets.php</code> under <code>'mail' =&gt; ['password' =&gt; '...']</code>. Emails wait in the queue until then.</div>
        <?php endif; ?>
        <div class="mail-stats">
            <div class="mail-stat"><span class="mail-stat__num"><?= e($counts['queued']) ?></span><span class="mail-stat__label">Waiting</span></div>
            <div class="mail-stat"><span class="mail-stat__num"><?= e($counts['sent_today']) ?></span><span class="mail-stat__label">Sent today</span></div>
            <div class="mail-stat"><span class="mail-stat__num"><?= e($counts['sent']) ?></span><span class="mail-stat__label">Sent in all</span></div>
            <div class="mail-stat"><span class="mail-stat__num"><?= e($counts['failed']) ?></span><span class="mail-stat__label">Failed</span></div>
        </div>
        <?php if ($job !== null && $job['last_run_at'] !== null): ?><p class="muted">Last automatic run: <?= e($when((string) $job['last_run_at'])) ?><?= $job['last_result'] ? ' · ' . e($job['last_result']) : '' ?></p><?php endif; ?>
        <div class="card section-card">
            <div class="decision__buttons">
                <form method="post" action="/studio/mail/send" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--green">Send waiting emails now</button></form>
                <?php if ($counts['failed'] > 0): ?><form method="post" action="/studio/mail/retry" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Try failed emails again</button></form><?php endif; ?>
            </div>
            <form method="post" action="/studio/mail/test" class="form mail-test"><?= $csrf->field() ?>
                <label class="field"><span class="field__label">Send a test email to</span><input type="email" name="to" required autocomplete="email" placeholder="you@example.com"></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Send test</button></div>
            </form>
        </div>
        <div class="card section-card" id="write">
            <h2 class="h3">Write an email</h2>
            <p>Send your own message to a group. Each person gets their own copy, in the SMART Circle style, from <?= e($contactEmail ?? '') ?>.
                Only people with an email address (and, for accounts, email turned on in their profile) receive it. Write <code>{name}</code> for each person’s first name.</p>
            <form method="post" action="/studio/mail/compose" class="form js-confirm" data-confirm="Send this email to everyone in the group you chose?"><?= $csrf->field() ?>
                <div class="field-row">
                    <label class="field"><span class="field__label">To</span>
                        <select name="group" required><?php foreach ($groups as $k => $label): ?><option value="<?= e($k) ?>" <?= ($old['group'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?> (<?= e($audience[$k] ?? 0) ?>)</option><?php endforeach; ?></select><?= $err('group') ?></label>
                    <label class="field field--short"><span class="field__label">Form <span class="muted">(students, guardians)</span></span>
                        <select name="form"><option value="0">All forms</option><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= e($f) ?>" <?= (string) ($old['form'] ?? '') === (string) $f ? 'selected' : '' ?>>Form <?= e($f) ?></option><?php endfor; ?></select><?= $err('form') ?></label>
                </div>
                <label class="field"><span class="field__label">Subject</span><input type="text" name="subject" maxlength="150" required value="<?= e($old['subject'] ?? '') ?>"><?= $err('subject') ?></label>
                <label class="field"><span class="field__label">Message</span><textarea name="body" rows="9" maxlength="6000" required><?= e($old['body'] ?? '') ?></textarea>
                    <span class="field__hint">Leave a blank line between paragraphs. Links become clickable. The SMART Circle footer and the site address are added at the bottom; replies come to your Zoho inbox.</span><?= $err('body') ?></label>
                <div class="form-actions"><button type="submit" class="btn btn--green">Put in the queue</button></div>
            </form>
        </div>
        <div class="card section-card">
            <h2 class="h3">Letters and wording</h2>
            <ul>
                <li><a href="/studio/templates">Message templates</a>: the wording of admission, guardian, reminder and digest messages.</li>
                <li><a href="/studio/invitations">Invitation letters</a>: invite a school teacher or a volunteer tutor, and email them the letter.</li>
            </ul>
        </div>
        <div class="card section-card" id="digest">
            <h2 class="h3">Weekly digest</h2>
            <p>Every Monday each student gets “Your week at SMART Circle” (new lessons and notes open to them, work due, feedback ready) and you get a summary of what is waiting. It arrives in the bell, and by email for those who want it.</p>
            <?php if ($digest !== null && $digest['last_run_at'] !== null): ?><p class="muted">Last sent <?= e($when((string) $digest['last_run_at'])) ?><?= $digest['last_result'] ? ' · ' . e($digest['last_result']) : '' ?></p><?php endif; ?>
            <form method="post" action="/studio/digest/send" class="inline-form js-confirm" data-confirm="Send this week’s digest to every student now?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Send the digest now</button></form>
        </div>
        <h2>Recent emails</h2>
        <?php if ($recent === []): ?><div class="card"><p>No emails yet.</p></div><?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>To</th><th>Subject</th><th>Status</th><th>When</th></tr></thead>
                <tbody><?php foreach ($recent as $m): ?>
                    <tr><td><?= e($m['to_name'] !== '' ? $m['to_name'] : $m['to_email']) ?><br><span class="muted"><?= e($m['to_email']) ?></span></td>
                        <td><?= e($m['subject']) ?></td>
                        <td><span class="chip<?= $m['status'] === 'sent' ? ' chip--lesson' : '' ?>"><?= e($labels[$m['status']] ?? $m['status']) ?></span>
                            <?php if ($m['last_error']): ?><br><span class="muted"><?= e($m['last_error']) ?></span><?php endif; ?></td>
                        <td><?= e($when($m['sent_at'] ?? $m['created_at'])) ?></td></tr>
                <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
