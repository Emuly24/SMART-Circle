<?php
/**
 * @var list<array<string, mixed>> $terms
 * @var int|null $termId
 * @var int $form
 * @var list<array<string, mixed>> $rows
 * @var array<string, string> $names
 * @var list<string> $subjects
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$pct = static fn (?float $p): string => $p === null ? '—' : rtrim(rtrim(number_format($p, 1), '0'), '.') . '%';
$shown = array_values(array_filter($subjects, static fn (string $s): bool => $rows === [] || array_filter($rows, static fn (array $r): bool => isset($r['grades']['subjects'][$s])) !== []));
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <div class="title-row">
            <h1>Grades</h1>
            <div class="title-row__actions"><a class="btn btn--small btn--ghost" href="/studio/grades/settings">Weights &amp; grade bands</a><a class="btn btn--small btn--ghost" href="/studio/calendar">Calendar</a></div>
        </div>
        <nav class="tabs tabs--small" aria-label="Form">
            <?php for ($f = 1; $f <= 4; $f++): ?><a href="/studio/grades?form=<?= $f ?>&amp;term=<?= e($termId) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= $f ?></a><?php endfor; ?>
        </nav>
        <?= $view->partial('term_picker', ['terms' => $terms, 'termId' => $termId, 'extra' => ['form' => (string) $form]]) ?>
        <?php if ($rows === []): ?>
            <div class="card"><p>No students in Form <?= e($form) ?> yet.</p></div>
        <?php else: ?>
            <form method="post" action="/studio/grades/issue" class="js-confirm" data-confirm="Issue term reports for every Form <?= e($form) ?> student? Each gets a printable report and a notification.">
                <?= $csrf->field() ?>
                <input type="hidden" name="term" value="<?= e($termId) ?>"><input type="hidden" name="form" value="<?= e($form) ?>">
                <div class="table-wrap">
                    <table class="table gradebook">
                        <thead><tr><th>Student</th><?php foreach ($shown as $s): ?><th><?= e($names[$s]) ?></th><?php endforeach; ?><th>Overall</th><th>Report comment</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): $g = $r['grades']; ?>
                            <tr>
                                <td><a href="/studio/grades/student/<?= e($r['student']['user_id']) ?>?term=<?= e($termId) ?>"><?= e($r['student']['full_name']) ?></a><?= $r['report'] ? ' <span class="chip chip--lesson">Report issued</span>' : '' ?></td>
                                <?php foreach ($shown as $s): $sg = $g['subjects'][$s] ?? null; ?>
                                    <td><?= $sg === null ? '<span class="muted">n/a</span>' : ($sg['grade'] !== null ? '<strong>' . e($sg['grade']['grade']) . '</strong> <span class="muted">' . e($pct($sg['percent'])) . '</span>' : '<span class="muted">—</span>') ?></td>
                                <?php endforeach; ?>
                                <td><?= $g['overall_grade'] !== null ? '<strong>' . e($g['overall_grade']['grade']) . '</strong> <span class="muted">' . e($pct($g['overall'])) . '</span>' : '—' ?></td>
                                <td><input type="text" name="comment[<?= e($r['student']['user_id']) ?>]" value="<?= e($r['report']['comment'] ?? '') ?>" maxlength="500" class="gradebook__comment" placeholder="e.g. Steady progress; keep practising units."></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions"><button type="submit" class="btn">Issue term reports</button></div>
            </form>
            <p class="note">Forms 1–2 use JCE letters (A–F); Forms 3–4 use MSCE points (1–9). Re-issuing replaces a report and its printed version.</p>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
