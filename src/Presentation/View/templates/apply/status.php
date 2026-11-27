<?php
/**
 * @var array<string, mixed> $app
 * @var bool $canTakeQuiz
 * @var list<array<string, mixed>> $letters
 */
$messages = [
    'submitted' => ['Received', 'The tutor has not reviewed your application yet.'],
    'shortlisted' => ['Shortlisted', 'Good news: you are on the shortlist. The tutor will decide soon.'],
    'admitted' => ['Admitted', 'Congratulations! You have a place in SMART Circle. The tutor will give you a setup code; then open "Set up my account".'],
    'waitlisted' => ['Waiting list', 'The seats for your form are full for now. You are on the waiting list and will be told if a seat opens.'],
    'declined' => ['Not admitted this time', 'Thank you for applying. You can still use the free lesson handouts in the library, and apply again next intake.'],
    'withdrawn' => ['Withdrawn', 'This application was withdrawn.'],
];
[$label, $message] = $messages[$app['status']] ?? ['Received', ''];
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/apply/status">Check another application</a></p>
        <h1><?= e($app['full_name']) ?></h1>
        <p class="muted">Reference <?= e($app['reference']) ?> · Form <?= e($app['form']) ?> · <?= e($app['school_name'] ?? '') ?></p>
        <div class="card status-card status-card--<?= e($app['status']) ?>">
            <p class="status-card__label"><?= e($label) ?></p>
            <p><?= e($message) ?></p>
            <?php if ($app['status'] === 'admitted'): ?>
                <p><a class="btn btn--green" href="/welcome">Set up my account</a></p>
            <?php elseif (in_array($app['status'], ['waitlisted', 'declined'], true)): ?>
                <p><a class="btn btn--ghost" href="/library">Open the free library</a></p>
            <?php endif; ?>
        </div>
        <?php if ($canTakeQuiz): ?>
            <div class="alert alert--info">You have not taken the entry quiz yet. <a href="/apply/quiz">Take it now</a> (20 minutes).</div>
        <?php elseif ($app['quiz_finished_at'] !== null): ?>
            <p>Entry quiz completed.</p>
        <?php endif; ?>
        <?php foreach ($letters as $letter): ?>
            <p><a class="btn btn--ghost" href="/letters/<?= e($letter['code']) ?>">Open my letter: <?= e($letter['title']) ?></a></p>
        <?php endforeach; ?>
    </div>
</section>
