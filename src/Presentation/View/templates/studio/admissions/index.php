<?php
/**
 * @var array<string, mixed> $intake
 * @var \SmartCircle\Application\Admissions\SeatChart $chart
 * @var list<array<string, mixed>> $applications
 * @var array{status: string, form: int} $filter
 * @var bool $isOpen
 */
$statusLabels = ['submitted' => 'To review', 'shortlisted' => 'Shortlisted', 'admitted' => 'Admitted', 'waitlisted' => 'Waiting list', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];
?>
<section class="section">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <div class="title-row">
            <h1>Admissions</h1>
            <div class="title-row__actions">
                <a class="btn btn--small btn--ghost" href="/studio/admissions/intake">Intake settings</a>
                <a class="btn btn--small btn--ghost" href="/apply">Type in a paper application</a>
            </div>
        </div>
        <p class="lead">
            <?= e($intake['name']) ?> ·
            <?= $isOpen ? '<span class="tag tag--ready">Applications open</span>' : '<span class="tag">Applications closed</span>' ?>
        </p>

        <div class="card"><?= $view->partial('seat_chart', ['chart' => $chart, 'detailed' => true]) ?></div>

        <form method="get" action="/studio/admissions" class="filters">
            <label>Status
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['submitted', 'shortlisted', 'admitted', 'waitlisted', 'declined'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filter['status'] === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Form
                <select name="form">
                    <option value="">All</option>
                    <?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= $filter['form'] === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?>
                </select>
            </label>
            <button type="submit" class="btn btn--small">Filter</button>
        </form>

        <?php if ($applications === []): ?>
            <div class="card"><p>No applications<?= $filter['status'] || $filter['form'] ? ' match this filter' : ' yet. Share the Apply page to start receiving them' ?>.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Applicant</th><th>Form</th><th>School</th><th>Quiz</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($applications as $a): ?>
                        <tr>
                            <td><a href="/studio/admissions/<?= e($a['id']) ?>"><?= e($a['full_name']) ?></a> <span class="muted"><?= $a['gender'] === 'female' ? '♀' : '♂' ?></span></td>
                            <td><?= e($a['form']) ?><?= $a['track'] === 'humanities' ? ' <span class="muted">hum.</span>' : '' ?></td>
                            <td><?= e($a['school_name'] ?? '') ?> <span class="muted"><?= e($a['district'] ?? '') ?></span></td>
                            <td><?= $a['quiz_total'] !== null ? e($a['quiz_score']) . '/' . e($a['quiz_total']) : '<span class="muted">not taken</span>' ?></td>
                            <td><span class="status status--<?= e($a['status']) ?>"><?= e($statusLabels[$a['status']] ?? $a['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
