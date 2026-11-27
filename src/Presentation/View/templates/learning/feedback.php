<?php
/**
 * @var bool $open
 * @var array<string, string> $pace
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container narrow">
        <h1>How are the lessons going?</h1>
        <?php if (!$open && $errors === []): ?>
            <div class="card"><p>Thank you. You have already given feedback this term, or the term has not started. You will be asked again next term.</p></div>
        <?php else: ?>
            <p class="lead">Three quick questions. <strong>Your answers are anonymous:</strong> your name is not saved with them, and the tutor only sees them once several students have answered.</p>
            <form method="post" action="/feedback" class="form card">
                <?= $csrf->field() ?>
                <fieldset class="field"><legend class="field__label">1. How clear are the lessons?</legend>
                    <div class="choice-row clarity-scale">
                        <?php foreach ([1 => 'Hard to follow', 2 => '', 3 => 'OK', 4 => '', 5 => 'Very clear'] as $n => $label): ?>
                            <label class="choice"><input type="radio" name="clarity" value="<?= $n ?>" <?= ($old['clarity'] ?? '') === (string) $n ? 'checked' : '' ?> required> <?= $n ?><?= $label !== '' ? ' · ' . e($label) : '' ?></label>
                        <?php endforeach; ?>
                    </div><?= $err('clarity') ?></fieldset>
                <fieldset class="field"><legend class="field__label">2. The pace is…</legend>
                    <div class="choice-row"><?php foreach ($pace as $k => $l): ?><label class="choice"><input type="radio" name="pace" value="<?= e($k) ?>" <?= ($old['pace'] ?? '') === $k ? 'checked' : '' ?> required> <?= e($l) ?></label><?php endforeach; ?></div><?= $err('pace') ?></fieldset>
                <label class="field"><span class="field__label">3. What helps you most? <span class="muted">(optional)</span></span><textarea name="helpful" rows="2" maxlength="500"><?= e($old['helpful'] ?? '') ?></textarea></label>
                <label class="field"><span class="field__label">One thing you would change <span class="muted">(optional)</span></span><textarea name="change" rows="2" maxlength="500"><?= e($old['change'] ?? '') ?></textarea></label>
                <p class="field__hint">Please do not write your name or anything that shows who you are.</p>
                <div class="form-actions"><button type="submit" class="btn">Send anonymously</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
