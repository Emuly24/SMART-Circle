<?php
/**
 * @var list<array<string, mixed>> $results
 * @var array<string, string> $exams
 * @var array<string, list<string>> $grades
 * @var array<string, string> $share
 * @var list<string> $subjects
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$all = array_values(array_unique(array_merge(...array_values($grades))));
$exam = (string) ($old['exam'] ?? array_key_last($exams));
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>My MANEB results</h1>
        <p class="lead">When your results come out, record them here. You choose who sees them.</p>
        <?php foreach ($results as $r): ?>
            <div class="card section-card result-saved">
                <h2 class="h3"><?= e($exams[$r['exam']] ?? strtoupper((string) $r['exam'])) ?> · <?= e($r['year']) ?>
                    <?php if ($r['verified_at'] !== null): ?><span class="chip chip--recorded">Checked by the tutor</span><?php endif; ?></h2>
                <p class="grade-chips"><?php foreach ((array) json_decode((string) $r['grades'], true) as $s => $g): ?><span class="badge badge--<?= e($s) ?>"><?= e($names[$s] ?? $s) ?>: <?= e($g) ?></span> <?php endforeach; ?></p>
                <p class="muted"><?= $r['subjects_passed'] !== null ? e($r['subjects_passed']) . ' of ' . e($r['subjects_sat'] ?? '?') . ' subjects passed. ' : '' ?><?= $r['points'] !== null ? e($r['points']) . ' points. ' : '' ?>Shared: <?= e($share[$r['share']] ?? $r['share']) ?></p>
            </div>
        <?php endforeach; ?>
        <form method="post" action="/results/mine" class="form card">
            <?= $csrf->field() ?>
            <h2 class="h3"><?= $results === [] ? 'Record your results' : 'Add or update results' ?></h2>
            <div class="field-row">
                <label class="field"><span class="field__label">Exam</span>
                    <select name="exam"><?php foreach ($exams as $k => $l): ?><option value="<?= e($k) ?>" <?= $exam === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('exam') ?></label>
                <label class="field"><span class="field__label">Year written</span><input type="number" name="year" min="2015" max="<?= e(date('Y')) ?>" value="<?= e($old['year'] ?? date('Y')) ?>"><?= $err('year') ?></label>
            </div>
            <fieldset class="field"><legend class="field__label">Grades in SMART Circle subjects <span class="muted">(JCE: A–F; MSCE: 1–9; leave empty if you did not write it)</span></legend>
                <div class="grade-inputs">
                    <?php foreach ($subjects as $s): ?>
                        <label class="field"><span class="field__label"><?= e($names[$s]) ?></span>
                            <select name="grades[<?= e($s) ?>]"><option value="">—</option><?php foreach ($all as $g): ?><option value="<?= e($g) ?>" <?= (string) (($old['grades'] ?? [])[$s] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select></label>
                    <?php endforeach; ?>
                </div><?= $err('grades') ?></fieldset>
            <div class="field-row">
                <label class="field"><span class="field__label">Subjects written (all)</span><input type="number" name="subjects_sat" min="1" max="15" value="<?= e($old['subjects_sat'] ?? '') ?>"><?= $err('subjects_sat') ?></label>
                <label class="field"><span class="field__label">Subjects passed</span><input type="number" name="subjects_passed" min="0" max="15" value="<?= e($old['subjects_passed'] ?? '') ?>"><?= $err('subjects_passed') ?></label>
                <label class="field"><span class="field__label">MSCE points <span class="muted">(best six)</span></span><input type="number" name="points" min="6" max="54" value="<?= e($old['points'] ?? '') ?>"><?= $err('points') ?></label>
            </div>
            <label class="field"><span class="field__label">What helped you, and advice for those coming next <span class="muted">(optional)</span></span><textarea name="reflection" rows="3" maxlength="1500"><?= e($old['reflection'] ?? '') ?></textarea><?= $err('reflection') ?></label>
            <fieldset class="field"><legend class="field__label">Who may see them</legend>
                <?php foreach ($share as $k => $l): ?><label class="choice choice--block"><input type="radio" name="share" value="<?= e($k) ?>" <?= ($old['share'] ?? 'private') === $k ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?>
                <span class="field__hint">Your name only appears after the tutor has seen your result slip. You can change this at any time.</span></fieldset>
            <div class="form-actions"><button type="submit" class="btn btn--small">Save results</button></div>
        </form>
    </div>
</section>
