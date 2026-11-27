<?php
/**
 * @var array<string, mixed>|null $intake
 * @var \SmartCircle\Application\Admissions\SeatChart|null $chart
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$open = $intake !== null && ($intake['closes_at'] === null || strtotime((string) $intake['closes_at']) > time());
?>
<section class="section">
    <div class="container narrow">
        <p class="crumbs"><a href="/studio/admissions">Admissions</a></p>
        <h1>Intake settings</h1>
        <?php if ($intake !== null): ?>
            <form method="post" action="/studio/admissions/intake" class="card form">
                <?= $csrf->field() ?>
                <label class="field">
                    <span class="field__label">Intake name</span>
                    <input type="text" name="name" value="<?= e($intake['name']) ?>" maxlength="100" required>
                </label>
                <input type="hidden" name="seats_per_group" value="<?= e($intake['seats_per_group']) ?>">
                <fieldset class="field seat-plan"><legend class="field__label">Seats in each form <span class="muted">(<?= e($chart?->totalSeats() ?? 24) ?> in total)</span></legend>
                    <div class="seat-plan__grid">
                        <span></span><span class="field__label">Girls</span><span class="field__label">Boys</span>
                        <?php for ($f = 1; $f <= 4; $f++): ?>
                            <span class="field__label">Form <?= $f ?></span>
                            <?php foreach (['female' => 'girls', 'male' => 'boys'] as $g => $label): $cell = $chart?->cell($f, $g); ?>
                                <input type="number" name="plan[<?= $f ?>][<?= $g ?>]" value="<?= e($cell['seats'] ?? $intake['seats_per_group']) ?>" min="0" max="<?= e(\SmartCircle\Application\Admissions\SeatChart::MAX_PER_CELL) ?>" aria-label="Form <?= $f ?> <?= e($label) ?>"<?= ($cell['admitted'] ?? 0) > 0 ? ' data-admitted="' . e($cell['admitted']) . '"' : '' ?>>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </div>
                    <span class="note">Raise a number to enrol more students in that form. Lowering it never removes anyone already admitted.</span>
                </fieldset>
                <label class="choice choice--block">
                    <input type="checkbox" name="is_open" value="1" <?= $open ? 'checked' : '' ?>> Accept applications
                </label>
                <label class="field">
                    <span class="field__label">Close automatically on <span class="muted">(optional)</span></span>
                    <input type="date" name="closes_at" value="<?= $open && $intake['closes_at'] ? e(substr((string) $intake['closes_at'], 0, 10)) : '' ?>">
                    <span class="note">Leave empty to keep accepting applications until all seats are filled.</span>
                </label>
                <button type="submit" class="btn">Save</button>
            </form>
        <?php endif; ?>

        <form method="post" action="/studio/admissions/intake" class="card form js-confirm" data-confirm="Start a new intake? The current intake's applications stay saved but new applications go to the new intake.">
            <?= $csrf->field() ?>
            <input type="hidden" name="action" value="new">
            <h2>Start a new intake</h2>
            <p class="note">For example at the start of a new school year.</p>
            <label class="field">
                <span class="field__label">Name</span>
                <input type="text" name="name" placeholder="2027 intake" maxlength="100" required>
            </label>
            <label class="field">
                <span class="field__label">Seats per form for girls, and for boys</span>
                <input type="number" name="seats_per_group" value="3" min="1" max="30" required>
                <span class="note">You can set each form separately after starting.</span>
            </label>
            <button type="submit" class="btn btn--ghost">Start new intake</button>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
