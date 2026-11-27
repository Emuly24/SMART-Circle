<?php
/**
 * The fields of a question (shared by the tutor's question bank and partner teachers' suggestions).
 * @var array<string, mixed> $q
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $types
 * @var list<array{id: int, form: int, subject: string, label: string}> $topicOptions
 * @var string|null $topicHint
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$d = is_array($q['data'] ?? null) ? $q['data'] : [];
$options = array_values(array_pad((array) ($d['options'] ?? []), 4, ''));
?>
            <div class="field-row">
                <label class="field"><span class="field__label">Form</span>
                    <select name="form"><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (int) $q['form'] === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('form') ?></label>
                <label class="field"><span class="field__label">Subject</span>
                    <select name="subject"><?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= $q['subject'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('subject') ?></label>
            </div>
            <label class="field"><span class="field__label">Topic <span class="muted">(students only get it once their school has taught this topic)</span></span>
                <select name="topic_id">
                    <option value="0">No topic (open to everyone in this form)</option>
                    <?php foreach ($topicOptions as $o): ?><option value="<?= e($o['id']) ?>" <?= (int) ($q['topic_id'] ?? 0) === $o['id'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?>
                </select>
                <span class="muted field__hint"><?= e($topicHint ?? 'Topics listed are for the form and subject this question was opened with. Save once after changing form or subject to see theirs.') ?></span>
            </label>
            <label class="field"><span class="field__label">Question type</span>
                <select name="type" class="js-question-type"><?php foreach ($types as $k => $n): ?><option value="<?= e($k) ?>" <?= $q['type'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('type') ?></label>
            <label class="field"><span class="field__label">Question</span>
                <textarea name="prompt" rows="4" maxlength="5000" placeholder="e.g. A minibus travels 120 km from Lilongwe to Dedza in 1.5 hours. What is its average speed?"><?= e($q['prompt'] ?? '') ?></textarea><?= $err('prompt') ?></label>

            <fieldset class="type-fields" data-type="mcq">
                <legend>Choices <span class="muted">(tick the correct one)</span></legend>
                <?php foreach ($options as $i => $o): ?>
                    <div class="mcq-option">
                        <input type="radio" name="correct" value="<?= $i ?>" aria-label="Choice <?= $i + 1 ?> is correct" <?= (string) ($d['correct'] ?? '') === (string) $i ? 'checked' : '' ?>>
                        <input type="text" name="options[]" value="<?= e($o) ?>" maxlength="300" placeholder="Choice <?= $i + 1 ?>">
                    </div>
                <?php endforeach; ?>
                <?= $err('options') ?><?= $err('correct') ?>
            </fieldset>

            <fieldset class="type-fields" data-type="numeric">
                <legend>Correct answer</legend>
                <div class="field-row">
                    <label class="field"><span class="field__label">Number</span><input type="text" name="answer" value="<?= e($d['answer'] ?? '') ?>" inputmode="decimal" placeholder="e.g. 80 or 3.2e4"><?= $err('answer') ?></label>
                    <label class="field"><span class="field__label">Unit <span class="muted">(optional)</span></span><input type="text" name="unit" value="<?= e($d['unit'] ?? '') ?>" maxlength="20" placeholder="e.g. km/h"></label>
                </div>
                <div class="field-row">
                    <label class="field"><span class="field__label">Allowed error (%)</span><input type="number" name="tolerance_pct" value="<?= e($d['tolerance_pct'] ?? 1) ?>" min="0" max="50" step="0.1"></label>
                    <label class="choice"><input type="checkbox" name="unit_required" value="1" <?= !empty($d['unit_required']) ? 'checked' : '' ?>> Unit required (half marks without it)</label>
                </div>
            </fieldset>

            <fieldset class="type-fields" data-type="grammar_fill">
                <legend>Accepted answers</legend>
                <label class="field"><span class="field__label">One per line. Capitals, spaces and a final full stop do not matter.</span>
                    <textarea name="accepted" rows="3" placeholder="has been&#10;has lived"><?= e(implode("\n", (array) ($d['accepted'] ?? []))) ?></textarea><?= $err('accepted') ?></label>
            </fieldset>

            <fieldset class="type-fields" data-type="short_text">
                <legend>Model answer <span class="muted">(only you see it, when marking)</span></legend>
                <textarea name="model_answer" rows="3"><?= e($d['model_answer'] ?? '') ?></textarea>
            </fieldset>

            <div class="field-row">
                <label class="field"><span class="field__label">Marks</span><input type="number" name="marks" value="<?= e($q['marks'] ?? 1) ?>" min="1" max="50"><?= $err('marks') ?></label>
                <label class="choice"><input type="checkbox" name="is_application" value="1" <?= !empty($q['is_application']) ? 'checked' : '' ?>> A real-life application question</label>
            </div>
