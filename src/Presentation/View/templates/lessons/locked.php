<?php
/**
 * A lesson whose topic the student's school has not taught yet.
 * @var array<string, mixed> $lesson
 * @var array{open: bool, basis: ?string, state: string} $access
 * @var array<string, string> $states
 * @var array<string, string> $subjects
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/lessons">Lessons</a></p>
        <div class="card locked-card">
            <p class="locked-card__icon" aria-hidden="true">🔒</p>
            <h1><?= e($lesson['title']) ?></h1>
            <p class="lead"><?= e($states[$access['state']] ?? 'Not open yet') ?>.</p>
            <p>SMART Circle goes over what your own school has already taught. This lesson opens for you as soon as your school
                reports it has taught the topic, and you will get a notification.</p>
            <p><a class="btn" href="/topics/<?= e($lesson['topic_id']) ?>">See the topic and your options</a></p>
        </div>
    </div>
</section>
