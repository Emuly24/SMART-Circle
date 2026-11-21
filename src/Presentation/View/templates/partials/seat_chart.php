<?php
/**
 * The 24 seats as a grid: Forms 1-4 x girls/boys.
 * @var \SmartCircle\Application\Admissions\SeatChart|null $chart
 * @var bool $detailed  tutor view: show pending and waiting-list counts
 */
if ($chart === null) {
    return;
}
$detailed ??= false;
?>
<div class="seats" role="table" aria-label="Seats available">
    <div class="seats__row seats__row--head" role="row">
        <span role="columnheader"></span>
        <span role="columnheader">Girls</span>
        <span role="columnheader">Boys</span>
    </div>
    <?php foreach ([1, 2, 3, 4] as $form): ?>
        <div class="seats__row" role="row">
            <span class="seats__form" role="rowheader">Form <?= e($form) ?></span>
            <?php foreach (['female', 'male'] as $gender): $c = $chart->cell($form, $gender); ?>
                <span class="seats__cell" role="cell">
                    <span class="seats__dots" aria-hidden="true">
                        <?php for ($i = 0; $i < $c['seats']; $i++): ?>
                            <span class="seat <?= $i < $c['admitted'] ? 'seat--taken' : 'seat--open' ?>"></span>
                        <?php endfor; ?>
                    </span>
                    <span class="seats__label">
                        <?= $c['open'] > 0 ? e($c['open']) . ' open' : 'Full' ?>
                        <?php if ($detailed): ?>
                            <span class="muted">· <?= e($c['pending']) ?> to review<?= $c['waitlisted'] ? ' · ' . e($c['waitlisted']) . ' waiting' : '' ?></span>
                        <?php elseif ($c['open'] === 0 && $c['waitlisted'] > 0): ?>
                            <span class="muted">· waiting list</span>
                        <?php endif; ?>
                    </span>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<p class="note seats__total"><?= e($chart->totalOpen()) ?> of <?= e($chart->totalSeats()) ?> seats open</p>
