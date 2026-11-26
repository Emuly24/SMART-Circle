<?php
/**
 * @var array<string, mixed> $note
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var list<array{id: int, form: int, subject: string, label: string}> $topicOptions
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<div class="field-row">
    <label class="field"><span class="field__label">Type</span>
        <select name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>" <?= ($note['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('kind') ?></label>
    <label class="field"><span class="field__label">Title</span><input type="text" name="title" value="<?= e($note['title'] ?? '') ?>" maxlength="200" required><?= $err('title') ?></label>
</div>
<div class="field-row">
    <label class="field"><span class="field__label">Form</span>
        <select name="form"><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (int) ($note['form'] ?? 0) === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('form') ?></label>
    <label class="field"><span class="field__label">Subject</span>
        <select name="subject"><?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= ($note['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('subject') ?></label>
</div>
<label class="field"><span class="field__label">Topic <span class="muted">(students get these notes once their school has taught it)</span></span>
    <select name="topic_id" class="js-topic-select">
        <option value="0">Choose…</option>
        <?php foreach ($topicOptions as $o): ?>
            <option value="<?= e($o['id']) ?>" data-form="<?= e($o['form']) ?>" data-subject="<?= e($o['subject']) ?>" <?= (int) ($note['topic_id'] ?? 0) === $o['id'] ? 'selected' : '' ?>>Form <?= e($o['form']) ?> · <?= e($o['label']) ?></option>
        <?php endforeach; ?>
    </select><?= $err('topic_id') ?></label>
<label class="field"><span class="field__label">Short summary <span class="muted">(optional, shown in the list)</span></span>
    <textarea name="summary" rows="2"><?= e($note['summary'] ?? '') ?></textarea></label>
