<?php
/**
 * @var array<string, mixed>|null $intake
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
                <label class="field">
                    <span class="field__label">Seats per form for girls, and for boys</span>
                    <input type="number" name="seats_per_group" value="<?= e($intake['seats_per_group']) ?>" min="1" max="10" required>
                    <span class="note">3 means 3 girls + 3 boys per form, so 24 seats in total.</span>
                </label>
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
                <input type="number" name="seats_per_group" value="3" min="1" max="10" required>
            </label>
            <button type="submit" class="btn btn--ghost">Start new intake</button>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
