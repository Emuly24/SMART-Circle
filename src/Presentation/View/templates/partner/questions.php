<?php
/**
 * @var array<string, mixed> $q
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $types
 * @var list<array<string, mixed>> $topicOptions
 * @var list<array<string, mixed>> $mine
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$status = ['pending' => 'Waiting for the tutor', 'accepted' => '✓ In the question bank', 'declined' => 'Not used'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>Suggest questions</h1>
        <p class="lead">You know what your students find hard. Suggest exercise or exam questions in your subjects; the tutor adds the good ones to the question bank with your name.</p>
        <form method="post" action="/partner/questions" class="form card question-form"><?= $csrf->field() ?>
            <?= $view->partial('question_editor', ['q' => $q, 'errors' => $errors, 'names' => $names, 'types' => $types, 'topicOptions' => $topicOptions,
                'topicHint' => 'Choose the topic from the same form and subject, so students only get it once their school has taught it.']) ?>
            <div class="form-actions"><button type="submit" class="btn btn--green">Send to the tutor</button></div>
        </form>
        <?php if ($mine !== []): ?>
            <h2>Your suggestions</h2>
            <ul class="review-history">
                <?php foreach ($mine as $m): ?>
                    <li class="review-history__item review-history__item--<?= $m['status'] === 'accepted' ? 'approved' : 'changes' ?>">
                        <p><strong><?= e($status[$m['status']] ?? $m['status']) ?></strong> <span class="muted">· Form <?= e($m['form']) ?> <?= e($names[$m['subject']] ?? '') ?><?= $m['topic_title'] ? ' · ' . e($m['topic_title']) : '' ?> · <?= e(date('j M Y', (int) strtotime((string) $m['created_at']))) ?></span></p>
                        <p><?= e(mb_strimwidth((string) $m['prompt'], 0, 200, '…')) ?></p>
                        <?php if ($m['tutor_note']): ?><p class="review-history__comment">Tutor: <?= e($m['tutor_note']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/question-form.js')) ?>" defer></script>
