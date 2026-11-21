<?php
/**
 * @var array{views: array<string, int>, exceptions: list<array<string, mixed>>, reports: array<string, int>} $report
 * @var list<array<string, mixed>> $schools
 * @var array<string, string> $names
 * @var int $staleDays
 */
$views = $report['views'];
$total = array_sum($views);
$pct = static fn (int $n): string => $total > 0 ? (string) round(100 * $n / $total) . '%' : '—';
$labels = ['school' => 'School had taught the topic', 'self' => 'Student’s class had covered it', 'exception' => 'Opened early by the tutor', 'untagged' => 'Older lesson without a topic', 'unknown' => 'Before this report existed'];
$r = $report['reports'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a> · <a href="/studio/checks">Checks</a></p>
        <h1>Honesty report</h1>
        <p class="lead">Proof that SMART Circle follows the schools: why each student was able to watch each lesson.</p>

        <div class="card section-card">
            <h2 class="h3">Lesson views</h2>
            <?php if ($total === 0): ?>
                <p class="muted">No lessons watched yet.</p>
            <?php else: ?>
                <p class="honesty-figure"><strong><?= e($pct(($views['school'] ?? 0) + ($views['self'] ?? 0))) ?></strong> of <?= e($total) ?> lesson views were on topics the student’s school or class had already taught.</p>
                <dl class="facts">
                    <?php foreach ($labels as $k => $label): if (!isset($views[$k])) { continue; } ?>
                        <dt><?= e($label) ?></dt><dd><?= e($views[$k]) ?> (<?= e($pct($views[$k])) ?>)</dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>

        <div class="card section-card">
            <h2 class="h3">Students’ own reports</h2>
            <dl class="facts">
                <dt>“My class has covered it”</dt><dd><?= e(($r['covered:unchecked'] ?? 0) + ($r['covered:confirmed'] ?? 0) + ($r['covered:rejected'] ?? 0)) ?> (<?= e($r['covered:confirmed'] ?? 0) ?> confirmed, <?= e($r['covered:rejected'] ?? 0) ?> rejected, <?= e($r['covered:unchecked'] ?? 0) ?> unchecked)</dd>
                <dt>“My class hasn’t reached it”</dt><dd><?= e(array_sum(array_filter($r, static fn (string $k): bool => str_starts_with($k, 'not_yet:'), ARRAY_FILTER_USE_KEY))) ?></dd>
            </dl>
        </div>

        <div class="card section-card">
            <h2 class="h3">Schools’ reports</h2>
            <p class="muted">A report is out of date after <?= e(intdiv($staleDays, 7)) ?> weeks without an update or “nothing changed”. During school holidays this is expected.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>School</th><th>Students</th><th>Last update</th></tr></thead>
                    <tbody>
                    <?php foreach ($schools as $s): ?>
                        <tr>
                            <td><a href="/studio/schools/<?= e($s['id']) ?>"><?= e($s['name']) ?></a></td>
                            <td><?= e($s['students']) ?></td>
                            <td><?= $s['fresh']['last'] === null ? '<span class="tag">Never</span>' : e(date('j M Y', strtotime((string) $s['fresh']['last']) ?: time())) . ($s['fresh']['stale'] ? ' <span class="tag">Out of date</span>' : '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card section-card">
            <h2 class="h3">Early access decisions</h2>
            <?php if ($report['exceptions'] === []): ?>
                <p class="muted">None yet.</p>
            <?php else: ?>
                <ul class="voices">
                    <?php foreach ($report['exceptions'] as $x): ?>
                        <li><strong><?= e($x['topic_title']) ?></strong> <span class="muted">(Form <?= e($x['form']) ?> <?= e($names[$x['subject']] ?? '') ?>)</span> · <?= e($x['full_name']) ?><?= $x['school_name'] ? ', ' . e($x['school_name']) : '' ?>:
                            <span class="chip <?= $x['status'] === 'approved' ? 'chip--lesson' : '' ?>"><?= e($x['status']) ?></span>
                            <p><?= e($x['reason']) ?></p></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
