<?php
/**
 * @var array<string, mixed> $at
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$a = $at['assessment'];
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1><?= e($a['title']) ?></h1>
        <?php if ($at['seconds_left'] !== null): ?>
            <p class="quiz-timer js-exam-timer" data-seconds="<?= e($at['seconds_left']) ?>" role="timer" aria-live="off">Time left: <span><?= e(gmdate('H:i:s', (int) $at['seconds_left'])) ?></span></p>
        <?php endif; ?>
        <?php if ($a['instructions']): ?><p class="assess-instructions"><?= nl2br(e($a['instructions'])) ?></p><?php endif; ?>
        <form method="post" action="/assessments/<?= e($a['id']) ?>/save" class="form exam-form" id="exam">
            <?= $csrf->field() ?>
            <?php foreach ($at['questions'] as $i => $q): $qid = (int) $q['id']; $mine = (string) ($at['answers'][$qid] ?? ''); ?>
                <fieldset class="card quiz-q">
                    <legend>Question <?= $i + 1 ?> <span class="chip"><?= e($fmt($q['max_marks'])) ?> mark<?= (float) $q['max_marks'] === 1.0 ? '' : 's' ?></span></legend>
                    <p class="quiz-q__text"><?= nl2br(e($q['prompt'])) ?></p>
                    <?php if ($q['type'] === 'mcq'): ?>
                        <?php foreach ((array) $q['data']['options'] as $k => $opt): ?>
                            <label class="choice choice--block"><input type="radio" name="answer[<?= $qid ?>]" value="<?= $k ?>" <?= $mine === (string) $k ? 'checked' : '' ?>> <?= e($opt) ?></label>
                        <?php endforeach; ?>
                    <?php elseif ($q['type'] === 'numeric'): ?>
                        <label class="field"><span class="field__label">Your answer<?= $q['data']['unit'] !== '' ? ' (include the unit)' : '' ?></span>
                            <input type="text" name="answer[<?= $qid ?>]" value="<?= e($mine) ?>" inputmode="decimal" autocomplete="off" placeholder="e.g. 2.5<?= $q['data']['unit'] !== '' ? ' ' . e($q['data']['unit']) : '' ?>"></label>
                    <?php elseif ($q['type'] === 'grammar_fill'): ?>
                        <label class="field"><span class="field__label">Your answer</span><input type="text" name="answer[<?= $qid ?>]" value="<?= e($mine) ?>" autocomplete="off" autocapitalize="none"></label>
                    <?php else: ?>
                        <label class="field"><span class="field__label">Your answer</span><textarea name="answer[<?= $qid ?>]" rows="4" maxlength="10000"><?= e($mine) ?></textarea></label>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
            <input type="hidden" name="action" value="save" class="js-exam-action">
            <div class="form-actions form-actions--sticky">
                <button type="submit" name="action" value="save" class="btn btn--ghost">Save and continue later</button>
                <button type="submit" name="action" value="submit" class="btn btn--green js-exam-submit">Submit</button>
            </div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/exam.js')) ?>" defer></script>
