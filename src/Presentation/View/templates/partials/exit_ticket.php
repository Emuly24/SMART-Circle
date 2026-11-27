<?php
/**
 * After a lesson: how well did you understand, one real-life use, what is still unclear.
 * @var int $lessonId
 * @var array<string, mixed>|null $ticket
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$u = (int) ($ticket['understanding'] ?? 0);
$labels = [1 => 'Lost', 2 => 'Unsure', 3 => 'Getting there', 4 => 'Good', 5 => 'I could teach it'];
?>
<section class="card exit-ticket" id="exit-ticket">
    <h2>Exit ticket</h2>
    <p class="muted"><?= $ticket === null ? 'Two minutes after the lesson. The tutor reads every one and uses them to plan.' : 'Thank you! You can change your answers.' ?></p>
    <form method="post" action="/lessons/<?= e($lessonId) ?>/exit-ticket" class="form"><?= $csrf->field() ?>
        <fieldset class="field"><legend class="field__label">How well did you understand this lesson?</legend>
            <div class="choice-row"><?php foreach ($labels as $n => $label): ?>
                <label class="choice"><input type="radio" name="understanding" value="<?= e($n) ?>" <?= $u === $n ? 'checked' : '' ?> required> <?= e($n) ?> · <?= e($label) ?></label>
            <?php endforeach; ?></div></fieldset>
        <label class="field"><span class="field__label">One place you could use this in real life</span><textarea name="real_life_use" rows="2" maxlength="1000"><?= e($ticket['real_life_use'] ?? '') ?></textarea></label>
        <label class="field"><span class="field__label">What is still unclear? <span class="muted">(optional)</span></span><textarea name="still_unclear" rows="2" maxlength="1000"><?= e($ticket['still_unclear'] ?? '') ?></textarea></label>
        <div class="form-actions"><button type="submit" class="btn btn--small btn--green"><?= $ticket === null ? 'Send' : 'Update' ?></button></div>
    </form>
</section>
