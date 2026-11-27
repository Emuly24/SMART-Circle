<?php
/**
 * @var array<string, string> $labels
 * @var bool $exists
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Try as</h1>
        <p class="lead">See and test every page exactly as students, teachers, alumni and team tutors do, using practice accounts at a practice school. Real students’ accounts are never used.</p>
        <div class="tile-grid">
            <?php foreach ($labels as $key => $label): ?>
                <form method="post" action="/studio/practice/enter" class="tile practice-tile">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="as" value="<?= e($key) ?>">
                    <h2><?= e($label) ?></h2>
                    <button type="submit" class="btn btn--small">Try as this person</button>
                </form>
            <?php endforeach; ?>
        </div>
        <div class="card section-card">
            <h2 class="h3">How it works</h2>
            <ul>
                <li>A bar at the top reminds you that you are in practice mode, with a “Back to admin” button.</li>
                <li>What you do there (requests, answers, messages, bookings) is stored as practice data, marked as practice.</li>
                <li>The practice school has a few topics marked as taught in every form, so you can see the honesty rule at work.</li>
            </ul>
            <?php if ($exists): ?>
                <form method="post" action="/studio/practice/reset" class="js-confirm" data-confirm="Remove all practice accounts and everything done with them?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--danger">Reset practice data</button></form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
