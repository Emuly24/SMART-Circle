<?php
/**
 * @var array<string, mixed> $a
 * @var array<string, string> $errors
 * @var list<array<string, mixed>> $questions
 * @var list<array<string, mixed>> $bank
 * @var list<array<string, mixed>> $submissions
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var array<string, string> $types
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$id = (int) $a['id'];
$total = array_sum(array_map(static fn (array $q): float => (float) $q['max_marks'], $questions));
$btn = static fn (string $action, string $label, string $class, string $confirm = '') => '<form method="post" action="/studio/assessments/' . e($id) . '/' . e($action) . '"'
    . ($confirm !== '' ? ' class="js-confirm" data-confirm="' . e($confirm) . '"' : '') . '>' . $csrf->field()
    . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
$toMark = count(array_filter($submissions, static fn (array $s): bool => $s['status'] === 'submitted'));
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/assessments">Exams &amp; assignments</a></p>
        <h1><?= e($a['title']) ?></h1>
        <p><span class="badge badge--<?= e($a['subject']) ?>"><?= e($names[$a['subject']] ?? '') ?></span>
            <span class="muted">Form <?= e($a['form']) ?> · <?= e($kinds[$a['kind']] ?? '') ?> · <?= e(count($questions)) ?> questions, <?= e(\SmartCircle\Application\Assessments\AssessmentService::fmt($total)) ?> marks</span>
            <span class="chip"><?= e(ucfirst((string) $a['status'])) ?></span><?= $a['results_at'] ? ' <span class="chip chip--lesson">Results released</span>' : '' ?></p>

        <div class="card assess-actions">
            <div class="decision__buttons">
                <?php if ($a['status'] === 'draft'): ?>
                    <?= $btn('publish', 'Publish to students', 'btn btn--small btn--green') ?>
                <?php elseif ($a['status'] === 'published'): ?>
                    <?= $btn('close', 'Close', 'btn btn--small btn--ghost', 'Close it? No new answers will be accepted.') ?>
                <?php else: ?>
                    <?= $btn('reopen', 'Reopen', 'btn btn--small btn--ghost') ?>
                <?php endif; ?>
                <?php if ($submissions !== []): ?>
                    <a class="btn btn--small" href="/studio/assessments/<?= e($id) ?>/submissions">Submissions (<?= e(count($submissions)) ?>)<?= $toMark > 0 ? ' · ' . e($toMark) . ' to mark' : '' ?></a>
                    <?php if ($a['kind'] !== 'weekly_check' && $a['results_at'] === null): ?>
                        <?= $btn('release', 'Release results', 'btn btn--small btn--green', 'Release the marks to every student who submitted?') ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?= $btn('delete', 'Delete', 'btn btn--small btn--danger', 'Delete this for good?') ?>
                <?php endif; ?>
            </div>
            <?php if ($a['kind'] === 'weekly_check'): ?><p class="note">Exercises show marks to students as soon as everything is marked.</p><?php endif; ?>
        </div>

        <h2 class="h3" id="questions">Questions</h2>
        <?php if ($questions === []): ?>
            <div class="card"><p>No questions yet. Add some from the bank below.</p></div>
        <?php else: ?>
            <ol class="question-list">
                <?php foreach ($questions as $i => $q): ?>
                    <li class="question-row question-row--static">
                        <div>
                            <span class="question-row__meta"><strong>Q<?= $i + 1 ?></strong>
                                <span class="chip"><?= e($types[$q['type']] ?? '') ?></span>
                                <span class="chip"><?= e(\SmartCircle\Application\Assessments\AssessmentService::fmt((float) $q['max_marks'])) ?> marks</span>
                                <?= $q['topic_title'] ? '<span class="muted">' . e($q['topic_title']) . '</span>' : '' ?></span>
                            <span class="question-row__prompt"><?= e(mb_strimwidth((string) $q['prompt'], 0, 200, '…')) ?></span>
                        </div>
                        <div class="question-row__tools">
                            <?php foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow): ?>
                                <form method="post" action="/studio/assessments/<?= e($id) ?>/questions/<?= e($q['id']) ?>/move"><?= $csrf->field() ?><input type="hidden" name="direction" value="<?= $dir ?>"><button type="submit" class="btn btn--small btn--ghost" aria-label="Move <?= $dir ?>"><?= $arrow ?></button></form>
                            <?php endforeach; ?>
                            <form method="post" action="/studio/assessments/<?= e($id) ?>/questions/<?= e($q['id']) ?>/remove"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove</button></form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="card section-card">
            <h2 class="h3">Add from the bank</h2>
            <?php if ($bank === []): ?>
                <p class="muted">No more questions for Form <?= e($a['form']) ?> <?= e($names[$a['subject']] ?? '') ?>. <a href="/studio/questions/new?form=<?= e($a['form']) ?>&amp;subject=<?= e($a['subject']) ?>">Write one</a>.</p>
            <?php else: ?>
                <ul class="bank-pick">
                    <?php foreach ($bank as $q): ?>
                        <li>
                            <span><span class="chip"><?= e($types[$q['type']] ?? '') ?></span> <?= e(mb_strimwidth((string) $q['prompt'], 0, 120, '…')) ?> <?= $q['topic_title'] ? '<span class="muted">(' . e($q['topic_title']) . ')</span>' : '' ?></span>
                            <form method="post" action="/studio/assessments/<?= e($id) ?>/questions"><?= $csrf->field() ?><input type="hidden" name="question_id" value="<?= e($q['id']) ?>"><button type="submit" class="btn btn--small">Add</button></form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <details class="card section-card"<?= $errors !== [] ? ' open' : '' ?>>
            <summary><strong>Edit details</strong></summary>
            <form method="post" action="/studio/assessments/<?= e($id) ?>" class="form">
                <?= $csrf->field() ?>
                <?= $view->partial('assessment_details', ['a' => $a, 'errors' => $errors, 'names' => $names, 'kinds' => $kinds]) ?>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save details</button></div>
            </form>
        </details>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
