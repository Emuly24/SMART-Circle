<?php
/**
 * @var list<array<string, mixed>> $results
 * @var list<array<string, mixed>> $revising
 * @var array<string, string> $exams
 * @var array<string, string> $share
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>MANEB</h1>
        <div class="card section-card">
            <h2 class="h3">In revision mode now</h2>
            <p class="muted">Three weeks before their MANEB exam, Form 2 and Form 4 students can open the whole exam syllabus. Lessons watched this way are logged as “MANEB revision” in the <a href="/studio/honesty">honesty report</a>.</p>
            <?php if ($revising === []): ?><p>Nobody right now.</p><?php else: ?>
                <ul class="timeline"><?php foreach ($revising as $s): ?>
                    <li><a href="/studio/students/<?= e($s['user_id']) ?>"><?= e($s['full_name']) ?></a> <span class="muted">· Form <?= e($s['form']) ?> · <?= e($s['window']['title']) ?> in <?= e($s['window']['days']) ?> days</span></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="card section-card">
            <h2 class="h3">Results students have recorded</h2>
            <p class="muted">Ask to see the result slip, then mark it checked. Only checked results can show a name publicly. <a href="/results">Public results page</a></p>
            <?php if ($results === []): ?><p>None yet.</p><?php else: ?>
                <ul class="student-list">
                    <?php foreach ($results as $r): ?>
                        <li class="card student-row">
                            <h3 class="h3"><?= e($r['full_name']) ?> <span class="muted">· <?= e($exams[$r['exam']] ?? $r['exam']) ?> <?= e($r['year']) ?></span></h3>
                            <p class="grade-chips"><?php foreach ((array) json_decode((string) $r['grades'], true) as $s => $g): ?><span class="badge badge--<?= e($s) ?>"><?= e($names[$s] ?? $s) ?>: <?= e($g) ?></span> <?php endforeach; ?></p>
                            <p class="muted"><?= $r['subjects_passed'] !== null ? e($r['subjects_passed']) . '/' . e($r['subjects_sat'] ?? '?') . ' passed · ' : '' ?><?= $r['points'] !== null ? e($r['points']) . ' points · ' : '' ?><?= e($share[$r['share']] ?? $r['share']) ?></p>
                            <?php if ($r['verified_at'] !== null): ?><p><span class="chip chip--recorded">Checked <?= e(date('j M Y', (int) strtotime((string) $r['verified_at']))) ?></span></p>
                            <?php else: ?><form method="post" action="/studio/results/<?= e($r['id']) ?>/verify" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">I have seen the result slip</button></form><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
