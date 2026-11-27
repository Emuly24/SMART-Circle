<?php
/**
 * @var array<string, mixed>|null $q questions, done_this_week, streak, accuracy
 * @var list<array<string, mixed>>|null $results after checking
 * @var array<string, string> $names
 */
$acc = $q['accuracy'] ?? ['answered' => 0, 'correct' => 0];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>Quick 5</h1>
        <p class="lead">Five quick questions on topics you have already met. A little, often, is how learning sticks.</p>
        <div class="stat-tiles">
            <div class="stat-tile"><span class="stat-tile__n"><?= e($q['streak'] ?? 0) ?></span><span>week<?= ($q['streak'] ?? 0) === 1 ? '' : 's' ?> in a row</span></div>
            <div class="stat-tile"><span class="stat-tile__n"><?= $acc['answered'] > 0 ? e(round(100 * $acc['correct'] / $acc['answered'])) . '%' : '—' ?></span><span>right in the last 30 days</span></div>
        </div>

        <?php if ($results !== null): $right = count(array_filter($results, static fn (array $r): bool => $r['correct'])); ?>
            <div class="card section-card quick5-result">
                <h2 class="h3"><?= e($right) ?> of <?= e(count($results)) ?> right<?= $right === count($results) && $results !== [] ? '. Excellent!' : '' ?></h2>
                <ol class="quick5-review">
                    <?php foreach ($results as $r): ?>
                        <li class="result-q result-q--<?= $r['correct'] ? 'got_it' : 'not_yet' ?>">
                            <p><span class="badge badge--<?= e($r['subject']) ?>"><?= e($names[$r['subject']] ?? $r['subject']) ?></span> <?= nl2br(e($r['prompt'])) ?></p>
                            <p><span class="fb fb--<?= $r['correct'] ? 'got_it' : 'not_yet' ?>"><?= \SmartCircle\Presentation\View\Icons::feedback($r['correct'] ? 'got_it' : 'not_yet') ?></span>
                                <?= $r['correct'] ? 'Got it' : 'Not yet' ?><?= $r['given'] !== '' ? ': you said “' . e($r['given']) . '”' : ': no answer' ?></p>
                            <?php if (!$r['correct'] && $r['right'] !== ''): ?><p class="muted">The answer: <?= e($r['right']) ?>. Look back at this topic’s lesson or notes.</p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($q === null || $q['questions'] === []): ?>
            <div class="card"><p class="muted">No questions are ready for you yet. They come from topics your school has taught; check back after the next lessons.</p></div>
        <?php else: ?>
            <?php if ($q['done_this_week'] && $results === null): ?><p class="note">You have done this week’s Quick 5. Here are five more for extra practice.</p><?php endif; ?>
            <form method="post" action="/quick5" class="exam-form">
                <?= $csrf->field() ?>
                <h2 class="h3"><?= $results !== null ? 'Five more' : 'This week’s questions' ?></h2>
                <?= $view->partial('question_fields', ['questions' => $q['questions'], 'answers' => []]) ?>
                <div class="form-actions"><button type="submit" class="btn">Check my answers</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
