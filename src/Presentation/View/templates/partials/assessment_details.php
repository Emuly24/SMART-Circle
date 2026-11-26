<?php
/**
 * Shared fields for an assessment's details.
 * @var array<string, mixed> $a
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$dt = static fn (?string $v): string => $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
?>
<div class="field-row">
    <label class="field"><span class="field__label">Kind</span>
        <select name="kind"><?php foreach ($kinds as $k => $n): ?><option value="<?= e($k) ?>" <?= ($a['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('kind') ?></label>
    <label class="field"><span class="field__label">Time limit for exams (minutes)</span>
        <input type="number" name="duration_min" value="<?= e($a['duration_min'] ?? '') ?>" min="5" max="240"><?= $err('duration_min') ?></label>
</div>
<label class="field"><span class="field__label">Title</span><input type="text" name="title" value="<?= e($a['title'] ?? '') ?>" maxlength="200" required><?= $err('title') ?></label>
<div class="field-row">
    <label class="field"><span class="field__label">Form</span>
        <select name="form"><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (int) ($a['form'] ?? 0) === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('form') ?></label>
    <label class="field"><span class="field__label">Subject</span>
        <select name="subject"><?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= ($a['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('subject') ?></label>
</div>
<div class="field-row">
    <label class="field"><span class="field__label">Opens <span class="muted">(optional)</span></span><input type="datetime-local" name="opens_at" value="<?= e($dt($a['opens_at'] ?? null)) ?>"><?= $err('opens_at') ?></label>
    <label class="field"><span class="field__label">Closes / due <span class="muted">(optional)</span></span><input type="datetime-local" name="closes_at" value="<?= e($dt($a['closes_at'] ?? null)) ?>"><?= $err('closes_at') ?></label>
</div>
<label class="field"><span class="field__label">Instructions <span class="muted">(optional)</span></span>
    <textarea name="instructions" rows="3" placeholder="e.g. Show your working. Use g = 10 N/kg."><?= e($a['instructions'] ?? '') ?></textarea></label>
