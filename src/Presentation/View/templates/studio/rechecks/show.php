<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$answer = (string) ($r['answer'] ?? '');
$shown = $r['type'] === 'mcq' && $answer !== '' ? (string) ($r['data']['options'][(int) $answer] ?? $answer) : $answer;
$current = $r['manual_score'] ?? $r['auto_score'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/rechecks">Re-check requests</a></p>
        <h1><?= e($r['full_name']) ?></h1>
        <p class="lead"><?= e($r['assessment_title']) ?></p>
        <div class="card">
            <p><?= nl2br(e($r['prompt'])) ?></p>
            <div class="mark-q__answer"><span class="muted">Their answer:</span> <?= $shown === '' ? '<em class="muted">No answer</em>' : nl2br(e($shown)) ?></div>
            <?php if ($r['type'] === 'mcq'): ?><p class="mark-q__model"><span class="muted">Correct:</span> <?= e($r['data']['options'][(int) ($r['data']['correct'] ?? 0)] ?? '') ?></p><?php endif; ?>
            <?php if ($r['type'] === 'numeric'): ?><p class="mark-q__model"><span class="muted">Correct:</span> <?= e($r['data']['answer'] ?? '') ?> <?= e($r['data']['unit'] ?? '') ?></p><?php endif; ?>
            <?php if ($r['type'] === 'short_text' && !empty($r['data']['model_answer'])): ?><p class="mark-q__model"><span class="muted">Model answer:</span> <?= nl2br(e($r['data']['model_answer'])) ?></p><?php endif; ?>
            <p>Current mark: <strong><?= e($current === null ? '0' : $fmt($current)) ?> / <?= e($fmt($r['max_marks'])) ?></strong></p>
            <h2 class="h3">Their reason</h2>
            <blockquote class="recheck-reason"><?= nl2br(e($r['reason'])) ?></blockquote>
        </div>
        <?php if ($r['status'] === 'open'): ?>
            <form method="post" action="/studio/rechecks/<?= e($r['id']) ?>" class="form card question-form">
                <?= $csrf->field() ?>
                <fieldset class="field"><legend class="field__label">Decision</legend>
                    <div class="choice-row">
                        <label class="choice"><input type="radio" name="decision" value="upheld" <?= ($old['decision'] ?? '') === 'upheld' ? 'checked' : '' ?>> The mark stays</label>
                        <label class="choice"><input type="radio" name="decision" value="changed" <?= ($old['decision'] ?? '') === 'changed' ? 'checked' : '' ?>> Change the mark</label>
                    </div><?= $err('decision') ?></fieldset>
                <label class="field"><span class="field__label">New mark <span class="muted">(if changing; 0 to <?= e($fmt($r['max_marks'])) ?>)</span></span>
                    <input type="number" name="new_score" value="<?= e($old['new_score'] ?? '') ?>" min="0" max="<?= e($fmt($r['max_marks'])) ?>" step="0.5"><?= $err('new_score') ?></label>
                <label class="field"><span class="field__label">Your reply to the student</span>
                    <textarea name="tutor_reply" rows="3" maxlength="2000" placeholder="Thank them for asking, and explain clearly."><?= e($old['tutor_reply'] ?? '') ?></textarea><?= $err('tutor_reply') ?></label>
                <div class="form-actions"><button type="submit" class="btn">Send reply</button></div>
            </form>
        <?php else: ?>
            <div class="card"><p><strong><?= $r['status'] === 'changed' ? 'Mark changed to ' . e($fmt($r['new_score'])) : 'Mark kept' ?></strong></p><p><?= nl2br(e($r['tutor_reply'])) ?></p></div>
        <?php endif; ?>
    </div>
</section>
