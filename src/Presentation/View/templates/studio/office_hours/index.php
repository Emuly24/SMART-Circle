<?php
/**
 * @var array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>} $slots
 * @var array<string, string> $modes
 * @var array<string, string> $audiences
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$row = static function (array $s) use ($modes, $audiences): string {
    return '<li><a href="/studio/office-hours/' . e($s['id']) . '">' . e(date('D j M, H:i', (int) strtotime((string) $s['starts_at']))) . '–' . e(date('H:i', (int) strtotime((string) $s['ends_at']))) . '</a>'
        . ' <span class="muted">· ' . e($modes[$s['mode']] ?? $s['mode']) . ' · ' . e($audiences[$s['audience']] ?? $s['audience']) . ' · ' . e($s['booked']) . '/' . e($s['capacity']) . ' booked'
        . ($s['status'] === 'cancelled' ? ' · cancelled' : '') . '</span></li>';
};
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Office hours</h1>
        <div class="card section-card">
            <h2 class="h3">Coming up</h2>
            <?php if ($slots['upcoming'] === []): ?><p class="muted">None planned.</p><?php else: ?><ul class="timeline"><?php foreach ($slots['upcoming'] as $s): ?><?= $row($s) ?><?php endforeach; ?></ul><?php endif; ?>
        </div>
        <form method="post" action="/studio/office-hours" class="form card">
            <?= $csrf->field() ?>
            <h2 class="h3">Offer a time</h2>
            <div class="field-row">
                <label class="field"><span class="field__label">Day</span><input type="date" name="date" required value="<?= e($old['date'] ?? '') ?>"><?= $err('date') ?></label>
                <label class="field"><span class="field__label">From</span><input type="time" name="start" required value="<?= e($old['start'] ?? '16:00') ?>"></label>
                <label class="field"><span class="field__label">To</span><input type="time" name="end" required value="<?= e($old['end'] ?? '17:00') ?>"><?= $err('end') ?></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">How</span><select name="mode"><?php foreach ($modes as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['mode'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('mode') ?></label>
                <label class="field"><span class="field__label">Places</span><input type="number" name="capacity" min="1" max="20" value="<?= e($old['capacity'] ?? '4') ?>"><?= $err('capacity') ?></label>
                <label class="field"><span class="field__label">Who can book</span><select name="audience"><?php foreach ($audiences as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['audience'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('audience') ?></label>
            </div>
            <label class="field"><span class="field__label">Note <span class="muted">(optional)</span></span><input type="text" name="note" maxlength="300" value="<?= e($old['note'] ?? '') ?>" placeholder="e.g. Form 4 Chemistry revision questions"></label>
            <div class="form-actions"><button type="submit" class="btn btn--small">Add and notify</button></div>
        </form>
        <?php if ($slots['past'] !== []): ?>
            <div class="card section-card"><h2 class="h3">Recent</h2><ul class="timeline"><?php foreach ($slots['past'] as $s): ?><?= $row($s) ?><?php endforeach; ?></ul></div>
        <?php endif; ?>
    </div>
</section>
