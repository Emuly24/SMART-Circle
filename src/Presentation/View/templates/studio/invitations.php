<?php
/**
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var array<string, string> $types
 * @var list<string> $titles
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $issued
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$chosen = (array) ($old['subjects'] ?? []);
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/partners">Partner teachers</a> · <a href="/studio/team">Team</a></p>
        <h1>Invitation letters</h1>
        <p class="lead">A signed letter inviting a school teacher to become a partner teacher, or a volunteer tutor to join the team. It explains why we need them and what they gain. Print it, save it as PDF, or share its link.</p>
        <form method="post" action="/studio/invitations" class="form card"><?= $csrf->field() ?>
            <fieldset class="field"><legend class="field__label">Who is it for?</legend>
                <?php foreach ($types as $k => $label): ?><label class="choice choice--block"><input type="radio" name="type" value="<?= e($k) ?>" <?= ($old['type'] ?? '') === $k ? 'checked' : '' ?> required> <?= e($label) ?></label><?php endforeach; ?>
                <?= $err('type') ?></fieldset>
            <div class="field-row">
                <label class="field field--short"><span class="field__label">Title</span><select name="title"><?php foreach ($titles as $t): ?><option value="<?= e($t) ?>" <?= ($old['title'] ?? '') === $t ? 'selected' : '' ?>><?= $t === '' ? '—' : e($t) ?></option><?php endforeach; ?></select><?= $err('title') ?></label>
                <label class="field"><span class="field__label">Full name</span><input type="text" name="full_name" maxlength="150" value="<?= e($old['full_name'] ?? '') ?>" required><?= $err('full_name') ?></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">School <span class="muted">(needed for a teacher)</span></span><input type="text" name="school" maxlength="150" value="<?= e($old['school'] ?? '') ?>"><?= $err('school') ?></label>
                <label class="field"><span class="field__label">District <span class="muted">(optional)</span></span><input type="text" name="district" maxlength="80" value="<?= e($old['district'] ?? '') ?>"></label>
            </div>
            <fieldset class="field"><legend class="field__label">Subjects</legend><div class="choice-grid">
                <?php foreach ($names as $k => $n): ?><label class="choice"><input type="checkbox" name="subjects[]" value="<?= e($k) ?>" <?= in_array($k, $chosen, true) ? 'checked' : '' ?>> <?= e($n) ?></label><?php endforeach; ?>
            </div><?= $err('subjects') ?></fieldset>
            <label class="field"><span class="field__label">A personal note <span class="muted">(optional, e.g. how you know them)</span></span><textarea name="note" rows="3" maxlength="800"><?= e($old['note'] ?? '') ?></textarea><?= $err('note') ?></label>
            <div class="form-actions"><button type="submit" class="btn btn--green">Write the letter</button></div>
        </form>
        <?php if ($issued !== []): ?>
            <h2>Letters written</h2>
            <ul class="review-history"><?php foreach ($issued as $d): ?>
                <li class="review-history__item review-history__item--approved"><p><a href="/letters/<?= e($d['code']) ?>"><?= e($d['title']) ?></a> <span class="muted">· <?= e(date('j M Y', (int) strtotime((string) $d['issued_at']))) ?> · code <?= e($d['code']) ?></span></p></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
