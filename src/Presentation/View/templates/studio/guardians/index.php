<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var array<string, string> $languages
 */
$states = ['confirmed' => ['Confirmed', 'chip--recorded'], 'waiting' => ['Not yet confirmed', 'chip--open'], 'declined' => ['Does not know the student', 'chip--closed']];
$waiting = count(array_filter($rows, static fn (array $r): bool => $r['status'] !== 'confirmed'));
$updates = count(array_filter($rows, static fn (array $r): bool => $r['monthly_sent'] === null));
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Guardians</h1>
        <p class="lead"><?= e($waiting) ?> still to confirm · <?= e($updates) ?> monthly update<?= $updates === 1 ? '' : 's' ?> still to send for <?= e(date('F')) ?>.</p>
        <?php if ($rows === []): ?><div class="card"><p class="muted">No students yet.</p></div><?php else: ?>
            <ul class="student-list">
                <?php foreach ($rows as $r): [$label, $chip] = $states[$r['status']]; ?>
                    <li class="card student-row">
                        <h2 class="h3"><a href="/studio/guardians/<?= e($r['user_id']) ?>"><?= e($r['full_name']) ?></a> <span class="muted">· Form <?= e($r['form']) ?></span></h2>
                        <p><?= e($r['guardian_name'] ?? 'No guardian name') ?><?= $r['guardian_relationship'] ? ' (' . e($r['guardian_relationship']) . ')' : '' ?> · <?= e($languages[$r['guardian_language']] ?? $r['guardian_language']) ?></p>
                        <p><span class="chip <?= e($chip) ?>"><?= e($label) ?></span>
                            <?php if ($r['monthly_sent'] !== null): ?><span class="chip chip--recorded"><?= e(date('F', (int) strtotime((string) $r['monthly_sent']))) ?> update sent</span>
                            <?php else: ?><span class="chip"><?= e(date('F')) ?> update not sent</span><?php endif; ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
