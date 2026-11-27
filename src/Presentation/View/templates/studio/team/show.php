<?php
/**
 * @var array<string, mixed> $t
 * @var string|null $code
 * @var array<string, string> $names
 */
$states = ['invited' => 'Invited, not yet agreed', 'active' => 'Active', 'declined' => 'Declined the agreement', 'removed' => 'Removed'];
$first = explode(' ', (string) $t['full_name'])[0];
$wa = $code !== null ? 'https://wa.me/' . ltrim((string) $t['phone'], '+') . '?text=' . rawurlencode("Hello $first, thank you for offering to teach with SMART Circle! To join, open the SMART Circle website, choose \"Set up my account\", and enter your phone number and this code: $code. It works for 14 days. You will then read our principles and decide whether to join.") : null;
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/team">The SMART Circle Team</a></p>
        <h1><?= e($t['full_name']) ?></h1>
        <p class="muted"><?= e(\SmartCircle\Support\PhoneNumber::display((string) $t['phone'])) ?><?= $t['email'] ? ' · ' . e($t['email']) : '' ?> · <?= e($states[$t['status']] ?? $t['status']) ?><?= $t['agreed_at'] ? ' (agreement ' . e($t['agreement_version']) . ', ' . e(date('j M Y', (int) strtotime((string) $t['agreed_at']))) . ')' : '' ?></p>
        <?php if ($code !== null): ?>
            <div class="card section-card setup-code">
                <h2 class="h3">Setup code</h2>
                <p class="reference"><?= e(implode(' ', str_split($code, 4))) ?></p>
                <div class="contact-buttons">
                    <a class="btn btn--small btn--green" href="<?= e($wa) ?>" target="_blank" rel="noopener">Send on WhatsApp</a>
                    <form method="post" action="/studio/team/<?= e($t['user_id']) ?>/code/hide" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">I have sent it, hide the code</button></form>
                </div>
            </div>
        <?php elseif ($t['status'] === 'invited'): ?>
            <form method="post" action="/studio/team/<?= e($t['user_id']) ?>/code" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Make a new setup code</button></form>
        <?php endif; ?>
        <form method="post" action="/studio/team/<?= e($t['user_id']) ?>/subjects" class="form card">
            <?= $csrf->field() ?>
            <fieldset class="field"><legend class="field__label">Subjects</legend>
                <div class="choice-row"><?php foreach ($names as $k => $l): ?><label class="choice"><input type="checkbox" name="subjects[]" value="<?= e($k) ?>" <?= in_array($k, $t['subject_list'], true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div></fieldset>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save subjects</button></div>
        </form>
        <form method="post" action="/studio/team/<?= e($t['user_id']) ?>/status" class="card js-confirm" data-confirm="<?= $t['status'] === 'removed' ? 'Let them sign in again?' : 'Remove from the team? They will no longer be able to sign in. Their lessons and marking are kept.' ?>">
            <?= $csrf->field() ?>
            <input type="hidden" name="active" value="<?= $t['status'] === 'removed' || $t['status'] === 'declined' ? '1' : '0' ?>">
            <button type="submit" class="btn btn--small <?= $t['status'] === 'removed' || $t['status'] === 'declined' ? 'btn--ghost' : 'btn--danger' ?>"><?= $t['status'] === 'removed' || $t['status'] === 'declined' ? 'Restore' : 'Remove from the team' ?></button>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
