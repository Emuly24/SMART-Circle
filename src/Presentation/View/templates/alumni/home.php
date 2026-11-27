<?php
/**
 * @var array<string, mixed> $p
 * @var array<string, string> $doing
 * @var array<string, string> $needs
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */
$v = static fn (string $k) => array_key_exists($k, $old) ? $old[$k] : ($p[$k] ?? null);
$myNeeds = array_key_exists('needs', $old) ? (array) $old['needs'] : array_filter(explode(',', (string) ($p['needs'] ?? '')));
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="eyebrow">Finished Form 4 in <?= e($p['left_year']) ?></p>
        <h1>What I am doing now</h1>
        <form method="post" action="/alumni" class="form card">
            <?= $csrf->field() ?>
            <label class="field"><span class="field__label">Right now I am</span>
                <select name="now_doing"><option value="">Choose…</option><?php foreach ($doing as $k => $l): ?><option value="<?= e($k) ?>" <?= $v('now_doing') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                <?php if (isset($errors['now_doing'])): ?><span class="field__error"><?= e($errors['now_doing']) ?></span><?php endif; ?></label>
            <div class="field-row">
                <label class="field"><span class="field__label">Where <span class="muted">(university, college, employer)</span></span><input type="text" name="institution" maxlength="150" value="<?= e($v('institution') ?? '') ?>"></label>
                <label class="field"><span class="field__label">Course or job</span><input type="text" name="programme" maxlength="150" value="<?= e($v('programme') ?? '') ?>"></label>
            </div>
            <fieldset class="field"><legend class="field__label">I would like help with</legend>
                <div class="choice-grid"><?php foreach ($needs as $k => $l): ?><label class="choice"><input type="checkbox" name="needs[]" value="<?= e($k) ?>" <?= in_array($k, $myNeeds, true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
                <span class="field__hint">The tutor sees this and will get in touch.</span></fieldset>
            <fieldset class="field"><legend class="field__label">Giving back</legend>
                <label class="choice choice--block"><input type="checkbox" name="can_mentor" value="1" <?= $v('can_mentor') ? 'checked' : '' ?>> I am happy to share my experience with current students (arranged through the tutor)</label>
                <label class="field"><span class="field__label">What I could help with <span class="muted">(optional)</span></span><input type="text" name="mentor_note" maxlength="300" value="<?= e($v('mentor_note') ?? '') ?>" placeholder="e.g. Applying to MUST, studying engineering"></label></fieldset>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
        </form>
        <div class="card section-card">
            <h2 class="h3">Useful to know</h2>
            <ul>
                <li><strong>Public universities:</strong> selection is run by the National Council for Higher Education (NCHE). Watch for the application window after MSCE results.</li>
                <li><strong>Loans and grants:</strong> the Higher Education Students’ Loans and Grants Board (HESLGB) opens applications each year. Book <a href="/office-hours">office hours</a> and we will go through the forms together.</li>
                <li><strong>Technical colleges:</strong> TEVET courses lead to good skilled jobs. They are worth considering alongside university.</li>
            </ul>
        </div>
    </div>
</section>
