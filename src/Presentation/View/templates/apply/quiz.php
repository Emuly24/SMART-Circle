<?php
/**
 * @var array<string, mixed> $app
 * @var list<array<string, mixed>> $questions
 * @var int $minutes
 * @var array<string, string> $subjects (unused)
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var callable $asset
 */
$names = \SmartCircle\Support\Subjects::NAMES;
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="eyebrow">Step 2 of 2 · Reference <?= e($app['reference']) ?></p>
        <h1>Entry quiz</h1>
        <p class="lead"><?= e(count($questions)) ?> questions for Form <?= e($app['form']) ?>. You have <?= e($minutes) ?> minutes. Do your best: it's fine not to know everything.</p>
        <p class="quiz-timer" id="quiz-timer" data-minutes="<?= e($minutes) ?>" data-started="<?= e(strtotime((string) ($app['quiz_started_at'] ?? 'now')) ?: time()) ?>" data-now="<?= e(time()) ?>" aria-live="polite"></p>

        <form method="post" action="/apply/quiz" class="form" id="quiz-form">
            <?= $csrf->field() ?>
            <?php foreach ($questions as $i => $q): ?>
                <fieldset class="card quiz-q">
                    <legend><span class="badge badge--<?= e($q['subject']) ?>"><?= e($names[$q['subject']] ?? $q['subject']) ?></span> Question <?= e($i + 1) ?></legend>
                    <p class="quiz-q__text"><?= nl2br(e($q['question'])) ?></p>
                    <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                        <label class="choice choice--block">
                            <input type="radio" name="q<?= e($q['id']) ?>" value="<?= $letter ?>">
                            <strong><?= strtoupper($letter) ?>.</strong> <?= e($q['option_' . $letter]) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            <button type="submit" class="btn btn--block btn--green">Submit my answers</button>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/quiz.js')) ?>" defer></script>
