<?php
/**
 * @var list<array<string, mixed>> $groups
 * @var array<string, string> $exams
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>MANEB results</h1>
        <p class="lead">How SMART Circle students did, from the results they chose to share. Grades are only counted with the student’s permission.</p>
        <?php if ($groups === []): ?>
            <div class="card"><p class="muted">No results shared yet. Our first students write MANEB in 2027.</p></div>
        <?php endif; ?>
        <?php foreach ($groups as $g): ?>
            <div class="card section-card">
                <h2 class="h3"><?= e($exams[$g['exam']] ?? $g['exam']) ?> · <?= e($g['year']) ?></h2>
                <p class="muted"><?= e($g['students']) ?> student<?= $g['students'] === 1 ? '' : 's' ?> shared their results; <?= e($g['verified']) ?> checked against the result slip.</p>
                <div class="table-wrap"><table class="results-table">
                    <thead><tr><th scope="col">Subject</th><th scope="col">Grade (students)</th><th scope="col">Passed</th></tr></thead>
                    <tbody>
                        <?php foreach ($g['subjects'] as $s => $d): ?>
                            <tr><th scope="row"><?= e($names[$s] ?? $s) ?></th>
                                <td><?= e(implode(', ', array_map(static fn ($k, $n): string => $k . ' (' . $n . ')', array_keys($d['grades']), $d['grades']))) ?></td>
                                <td><?= e($d['passed']) ?> of <?= e($d['total']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ($g['named'] !== []): ?>
                    <h3 class="h3">In their own words</h3>
                    <?php foreach ($g['named'] as $n): ?>
                        <blockquote class="quote"><p><?= nl2br(e($n['reflection'] ?? 'Proud to have been part of SMART Circle.')) ?></p><footer>— <?= e($n['name']) ?></footer></blockquote>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
