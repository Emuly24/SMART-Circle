<?php /** @var array<string, mixed> $r rows, summary */ $s = $r['summary']; ?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Impact</h1>
        <p class="lead">Where students started (entry quiz) and where they are now (this term’s average), with how much they practise.</p>
        <p class="note">A guide, not an exact measure: the entry quiz and term work test different things. Use it alongside what you see in lessons and, later, MANEB results.</p>
        <?php if ($s['compared'] > 0): ?>
            <div class="stat-tiles"><div class="stat-tile"><span class="stat-tile__n"><?= e($s['up']) ?> / <?= e($s['compared']) ?></span><span>students above their starting point</span></div></div>
        <?php endif; ?>
        <div class="card section-card">
            <h2 class="h3">By form</h2>
            <?php if ($s['forms'] === []): ?><p class="muted">No active students yet.</p><?php else: ?>
                <div class="table-wrap"><table class="results-table"><thead><tr><th scope="col">Form</th><th scope="col">Students</th><th scope="col">Entry quiz (average)</th><th scope="col">This term (average)</th></tr></thead><tbody>
                    <?php foreach ($s['forms'] as $f => $d): ?><tr><th scope="row">Form <?= e($f) ?></th><td><?= e($d['students']) ?></td><td><?= $d['entry'] !== null ? e($d['entry']) . '%' : '—' ?></td><td><?= $d['now'] !== null ? e($d['now']) . '%' : '—' ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
        <div class="card section-card">
            <h2 class="h3">Each student</h2>
            <?php if ($r['rows'] === []): ?><p class="muted">No active students yet.</p><?php else: ?>
                <div class="table-wrap"><table class="results-table"><thead><tr><th scope="col">Student</th><th scope="col">Form</th><th scope="col">Entry quiz</th><th scope="col">This term</th><th scope="col">Lessons watched</th><th scope="col">Quick 5 (30 days)</th></tr></thead><tbody>
                    <?php foreach ($r['rows'] as $row): $better = $row['entry'] !== null && $row['now'] !== null && $row['now'] > $row['entry']; ?>
                        <tr><th scope="row"><a href="/studio/students/<?= e($row['id']) ?>"><?= e($row['full_name']) ?></a></th><td><?= e($row['form']) ?></td>
                            <td><?= $row['entry'] !== null ? e($row['entry']) . '%' : '—' ?></td>
                            <td><?= $row['now'] !== null ? e($row['now']) . '%' . ($better ? ' <span class="chip chip--recorded">up</span>' : '') : '—' ?></td>
                            <td><?= e($row['lessons']) ?></td><td><?= $row['quick5'] !== null ? e($row['quick5']) . '% of ' . e($row['q_answered']) : '—' ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</section>
