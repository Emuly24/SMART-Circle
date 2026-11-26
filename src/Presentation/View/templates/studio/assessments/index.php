<?php
/**
 * @var list<array<string, mixed>> $assessments
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <div class="title-row">
            <h1>Exams &amp; assignments</h1>
            <div class="title-row__actions">
                <a class="btn btn--small btn--ghost" href="/studio/questions">Question bank</a>
                <a class="btn btn--small" href="/studio/assessments/new">New</a>
            </div>
        </div>
        <p class="lead">Students only get an assessment once their school has taught every topic in it.</p>
        <?php if ($assessments === []): ?>
            <div class="card"><p>Nothing yet. Write some questions in the bank, then create an exam, assignment or exercise.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Title</th><th>Kind</th><th>Status</th><th>Answers</th></tr></thead>
                    <tbody>
                    <?php foreach ($assessments as $a): ?>
                        <tr>
                            <td><a href="/studio/assessments/<?= e($a['id']) ?>"><?= e($a['title']) ?></a><br><span class="muted">Form <?= e($a['form']) ?> <?= e($names[$a['subject']] ?? '') ?> · <?= e($a['questions']) ?> questions</span></td>
                            <td><?= e($kinds[$a['kind']] ?? $a['kind']) ?></td>
                            <td><span class="status status--<?= e($a['status'] === 'published' ? 'admitted' : ($a['status'] === 'draft' ? 'submitted' : 'declined')) ?>"><?= e(ucfirst((string) $a['status'])) ?></span><?= $a['results_at'] ? ' <span class="chip chip--lesson">Results out</span>' : '' ?></td>
                            <td><?= e($a['submitted']) ?><?= (int) $a['to_mark'] > 0 ? ' <span class="tag">' . e($a['to_mark']) . ' to mark</span>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
