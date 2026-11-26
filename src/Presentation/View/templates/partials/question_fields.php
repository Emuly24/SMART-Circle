<?php
/**
 * Answer fields for questions (exam page and checkpoints in notes). Questions arrive WITHOUT answers.
 * @var list<array<string, mixed>> $questions
 * @var array<int, string> $answers saved answers by question id
 */
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
?>
<?php foreach ($questions as $i => $q): $qid = (int) $q['id']; $mine = (string) ($answers[$qid] ?? ''); ?>
    <fieldset class="card quiz-q">
        <legend>Question <?= $i + 1 ?> <span class="chip"><?= e($fmt($q['max_marks'])) ?> mark<?= (float) $q['max_marks'] === 1.0 ? '' : 's' ?></span></legend>
        <p class="quiz-q__text"><?= nl2br(e($q['prompt'])) ?></p>
        <?php if ($q['type'] === 'mcq'): ?>
            <?php foreach ((array) $q['data']['options'] as $k => $opt): ?>
                <label class="choice choice--block"><input type="radio" name="answer[<?= $qid ?>]" value="<?= $k ?>" <?= $mine === (string) $k ? 'checked' : '' ?>> <?= e($opt) ?></label>
            <?php endforeach; ?>
        <?php elseif ($q['type'] === 'numeric'): ?>
            <label class="field"><span class="field__label">Your answer<?= ($q['data']['unit'] ?? '') !== '' ? ' (include the unit)' : '' ?></span>
                <input type="text" name="answer[<?= $qid ?>]" value="<?= e($mine) ?>" inputmode="decimal" autocomplete="off" placeholder="e.g. 2.5<?= ($q['data']['unit'] ?? '') !== '' ? ' ' . e($q['data']['unit']) : '' ?>"></label>
        <?php elseif ($q['type'] === 'grammar_fill'): ?>
            <label class="field"><span class="field__label">Your answer</span><input type="text" name="answer[<?= $qid ?>]" value="<?= e($mine) ?>" autocomplete="off" autocapitalize="none"></label>
        <?php else: ?>
            <label class="field"><span class="field__label">Your answer</span><textarea name="answer[<?= $qid ?>]" rows="4" maxlength="10000"><?= e($mine) ?></textarea></label>
        <?php endif; ?>
    </fieldset>
<?php endforeach; ?>
