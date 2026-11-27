<?php
/**
 * @var list<array<string, mixed>> $members
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$states = ['invited' => 'Invited, not yet agreed', 'active' => 'Active', 'declined' => 'Declined', 'removed' => 'Removed'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>The SMART Circle Team</h1>
        <p class="lead">Volunteer tutors who teach and mark in the subjects you give them. Before they start, they read the duties and our principles and agree to them.</p>
        <p><a class="btn btn--small btn--ghost" href="/studio/invitations?type=tutor_invitation">Write an invitation letter to a tutor</a></p>
        <?php if ($members === []): ?><div class="card"><p class="muted">No team tutors yet.</p></div><?php else: ?>
            <ul class="student-list">
                <?php foreach ($members as $t): ?>
                    <li class="card student-row">
                        <h2 class="h3"><a href="/studio/team/<?= e($t['user_id']) ?>"><?= e($t['full_name']) ?></a></h2>
                        <p class="muted"><?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $t['subject_list']))) ?> · <?= e($states[$t['status']] ?? $t['status']) ?><?= $t['agreed_at'] ? ' on ' . e(date('j M Y', (int) strtotime((string) $t['agreed_at']))) : '' ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="/studio/team" class="form card">
            <?= $csrf->field() ?>
            <h2 class="h3">Invite a tutor</h2>
            <div class="field-row">
                <label class="field"><span class="field__label">Full name</span><input type="text" name="full_name" maxlength="150" required value="<?= e($old['full_name'] ?? '') ?>"><?= $err('full_name') ?></label>
                <label class="field"><span class="field__label">Phone</span><input type="tel" name="phone" required value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel"><?= $err('phone') ?></label>
            </div>
            <label class="field"><span class="field__label">Email <span class="muted">(optional)</span></span><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>"><?= $err('email') ?></label>
            <fieldset class="field"><legend class="field__label">Subjects they will teach</legend>
                <div class="choice-row"><?php foreach ($names as $k => $l): ?><label class="choice"><input type="checkbox" name="subjects[]" value="<?= e($k) ?>" <?= in_array($k, (array) ($old['subjects'] ?? []), true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div><?= $err('subjects') ?></fieldset>
            <div class="form-actions"><button type="submit" class="btn btn--small">Create invitation</button></div>
        </form>
    </div>
</section>
