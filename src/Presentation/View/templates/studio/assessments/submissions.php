<?php
/**
 * @var array<string, mixed> $a
 * @var list<array<string, mixed>> $rows
 */
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/assessments/<?= e($a['id']) ?>"><?= e($a['title']) ?></a></p>
        <h1>Submissions</h1>
        <?php if ($rows === []): ?>
            <div class="card"><p>No one has started yet.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Student</th><th>Status</th><th>Score</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $s): ?>
                        <tr>
                            <td><?= $s['status'] === 'in_progress' ? e($s['full_name']) : '<a href="/studio/submissions/' . e($s['id']) . '">' . e($s['full_name']) . '</a>' ?></td>
                            <td><?= match ($s['status']) { 'submitted' => '<span class="tag">To mark</span>', 'marked' => '<span class="chip chip--lesson">Marked</span>', default => '<span class="chip">In progress</span>' } ?></td>
                            <td><?= $s['score'] !== null ? e($fmt($s['score'])) . ' / ' . e($fmt($s['max_score'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
