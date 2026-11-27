<?php
/**
 * One student's term grades by subject, with the category breakdown.
 * @var array<string, mixed> $g
 * @var array<string, string> $names
 * @var array<string, string> $categories
 */
$pct = static fn (?float $p): string => $p === null ? '—' : rtrim(rtrim(number_format($p, 1), '0'), '.') . '%';
?>
<div class="table-wrap grade-table">
    <table class="table">
        <thead><tr><th>Subject</th><th>Grade</th><th>Term %</th><?php foreach ($categories as $label): ?><th class="grade-table__cat"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($g['subjects'] as $key => $s): ?>
            <tr>
                <td><span class="badge badge--<?= e($key) ?>"><?= e($names[$key] ?? $key) ?></span></td>
                <td><?= $s['grade'] !== null ? '<strong class="grade grade--' . e($g['scale']) . '">' . e($s['grade']['grade']) . '</strong> <span class="muted">' . e($s['grade']['descriptor']) . '</span>' : '<span class="muted">No marked work yet</span>' ?></td>
                <td><?= e($pct($s['percent'])) ?></td>
                <?php foreach ($categories as $cat => $label): ?>
                    <td class="grade-table__cat"><?= isset($s['categories'][$cat]) ? e($pct($s['categories'][$cat]['percent'])) . ' <span class="muted">(' . e($s['categories'][$cat]['count']) . ')</span>' : '<span class="muted">—</span>' ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($g['overall'] !== null): ?>
    <p class="grade-overall">Overall: <strong><?= e($pct($g['overall'])) ?></strong><?= $g['overall_grade'] !== null ? ' · ' . e($g['scale'] === 'jce' ? 'JCE' : 'MSCE') . ' ' . e($g['overall_grade']['grade']) . ' (' . e($g['overall_grade']['descriptor']) . ')' : '' ?>
        <?= $g['graded'] < $g['subject_count'] ? '<span class="muted">· from ' . e($g['graded']) . ' of ' . e($g['subject_count']) . ' subjects with marked work so far</span>' : '' ?></p>
<?php endif; ?>
