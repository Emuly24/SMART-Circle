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
            <?= $view->partial('question_fields', ['questions' => $at['questions'], 'answers' => $at['answers']]) ?>
            <input type="hidden" name="action" value="save" class="js-exam-action">
            <div class="form-actions form-actions--sticky">
                <button type="submit" name="action" value="save" class="btn btn--ghost">Save and continue later</button>
                <button type="submit" name="action" value="submit" class="btn btn--green js-exam-submit">Submit</button>
            </div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/exam.js')) ?>" defer></script>
