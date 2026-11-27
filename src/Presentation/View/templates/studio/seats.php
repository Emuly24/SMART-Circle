<?php
/**
 * @var list<array<string, mixed>> $quiet
 * @var array<int, string> $errors
 * @var array<int, array<string, string>> $old
 * @var int $nudgeAfter
 * @var int $reviewAfter
 * @var array<string, mixed>|null $job
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$review = array_values(array_filter($quiet, static fn (array $s): bool => $s['state'] === 'review'));
$nudged = array_values(array_filter($quiet, static fn (array $s): bool => $s['state'] === 'nudge'));
$paused = array_values(array_filter($quiet, static fn (array $s): bool => $s['state'] === 'paused'));
$when = static fn (?string $t): string => $t ? date('j M', (int) strtotime($t)) : '';
$card = static function (array $s, bool $full) use ($csrf, $errors, $old, $when): string {
    $id = (int) $s['id'];
    $first = explode(' ', trim((string) $s['full_name']))[0];
    $o = $old[$id] ?? [];
    $h = '<li class="card seat-card" id="seat-' . e($id) . '"><div class="seat-card__head"><a href="/studio/students/' . e($id) . '"><strong>' . e($s['full_name']) . '</strong></a>'
        . ' <span class="muted">Form ' . e($s['form']) . ($s['school_name'] ? ' · ' . e($s['school_name']) : '') . '</span></div>'
        . '<p class="seat-card__days">' . ($s['never'] ? 'Not active since being admitted ' : 'Last active ') . e($s['days']) . ' days ago'
        . ($s['last_check'] ? ' · <span class="muted">' . e(['nudge' => 'Nudged', 'pause' => 'Paused', 'released' => 'Released'][$s['last_check']] ?? '') . ' ' . e($when($s['last_check_at'])) . '</span>' : '') . '</p>';
    if ($s['state'] === 'paused') {
        return $h . '<p class="muted">Checks paused until ' . e(date('j F', (int) strtotime((string) $s['paused_until']))) . ': ' . e($s['last_check_note']) . '</p></li>';
    }
    if (isset($errors[$id])) {
        $h .= '<div class="alert alert--error">' . e($errors[$id]) . '</div>';
    }
    $h .= '<div class="decision__buttons"><form method="post" action="/studio/seats/' . e($id) . '/nudge" class="inline-form">' . $csrf->field()
        . '<button type="submit" class="btn btn--small">Send ' . e($first) . ' a friendly nudge</button></form>'
        . '<a class="btn btn--small btn--ghost" href="/studio/students/' . e($id) . '">Call or WhatsApp</a></div>';
    $h .= '<details' . (isset($o['until']) ? ' open' : '') . '><summary>They have a good reason: pause the checks</summary>'
        . '<form method="post" action="/studio/seats/' . e($id) . '/pause" class="form">' . $csrf->field()
        . '<div class="field-row"><label class="field"><span class="field__label">Until</span><input type="date" name="until" value="' . e($o['until'] ?? '') . '" required></label>'
        . '<label class="field"><span class="field__label">Reason</span><input type="text" name="reason" maxlength="500" value="' . e($o['reason'] ?? '') . '" placeholder="e.g. sick, school exams, no phone this month" required></label></div>'
        . '<div class="form-actions"><button type="submit" class="btn btn--small btn--ghost">Pause checks</button></div></form></details>';
    if ($full) {
        $h .= '<details' . (isset($o['release']) ? ' open' : '') . '><summary>Free the seat</summary>'
            . '<p class="muted">Only after you have tried to reach ' . e($first) . ' and their guardian. Their account is switched off and the seat opens for '
            . ($s['waiting'] > 0 ? e($s['waiting']) . ' person' . ($s['waiting'] === 1 ? '' : 's') . ' on the waiting list' : 'a new applicant') . '. Everything they did is kept.</p>'
            . '<form method="post" action="/studio/seats/' . e($id) . '/release" class="form js-confirm" data-confirm="Free ' . e($first) . '’s seat? Their account will be switched off.">' . $csrf->field()
            . '<label class="field"><span class="field__label">Why</span><input type="text" name="reason" maxlength="500" value="' . e($o['release'] ?? '') . '" placeholder="e.g. moved to another district; no reply for a month" required></label>'
            . '<div class="form-actions"><button type="submit" class="btn btn--small btn--danger">Free the seat</button></div></form></details>';
    }

    return $h . '</li>';
};
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/students">Students</a></p>
        <h1>Seat activity</h1>
        <p class="lead">Every seat is precious. Students quiet for <?= e($nudgeAfter) ?> days get a friendly automatic nudge; after <?= e($reviewAfter) ?> days they appear here so you can check in.</p>
        <?php if ($job !== null && $job['last_run_at'] !== null): ?><p class="muted">Automatic check: <?= e(date('j M, H:i', (int) strtotime((string) $job['last_run_at']))) ?><?= $job['last_result'] ? ' · ' . e($job['last_result']) : '' ?></p><?php endif; ?>

        <h2>Need your check-in (<?= e(count($review)) ?>)</h2>
        <?php if ($review === []): ?><div class="card"><p>No one has been quiet for <?= e($reviewAfter) ?> days. 🎉</p></div>
        <?php else: ?><ul class="seat-list"><?php foreach ($review as $s) { echo $card($s, true); } ?></ul><?php endif; ?>

        <?php if ($nudged !== []): ?>
            <h2>Quiet for <?= e($nudgeAfter) ?>+ days (<?= e(count($nudged)) ?>)</h2>
            <ul class="seat-list"><?php foreach ($nudged as $s) { echo $card($s, false); } ?></ul>
        <?php endif; ?>
        <?php if ($paused !== []): ?>
            <h2>Paused (<?= e(count($paused)) ?>)</h2>
            <ul class="seat-list"><?php foreach ($paused as $s) { echo $card($s, false); } ?></ul>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
