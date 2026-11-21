<?php
/**
 * Coverage progress bars: main topics a school has taught, per form and subject.
 * @var array<string, array{covered: int, total: int}> $progress
 * @var array<string, string> $names
 * @var list<string> $subjects
 */
?>
<div class="progress-table">
    <div class="progress-table__row progress-table__row--head"><span></span><?php for ($f = 1; $f <= 4; $f++): ?><span>Form <?= $f ?></span><?php endfor; ?></div>
    <?php foreach ($subjects as $s): ?>
        <div class="progress-table__row">
            <span class="progress-table__subject"><?= e($names[$s] ?? $s) ?></span>
            <?php for ($f = 1; $f <= 4; $f++):
                $p = $progress[$f . ' ' . $s] ?? ['covered' => 0, 'total' => 0];
                $pct = $p['total'] > 0 ? (int) round(100 * $p['covered'] / $p['total']) : 0; ?>
                <span class="meter" title="<?= e($p['covered']) ?> of <?= e($p['total']) ?> topics taught">
                    <meter min="0" max="100" value="<?= e($pct) ?>"></meter>
                    <small><?= e($p['covered']) ?>/<?= e($p['total']) ?></small>
                </span>
            <?php endfor; ?>
        </div>
    <?php endforeach; ?>
</div>
