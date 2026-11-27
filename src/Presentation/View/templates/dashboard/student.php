<?php
/**
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var array<string, mixed>|null $pledge
 * @var list<array<string, mixed>> $feedback
 * @var array<string, mixed>|null $calendar
 */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <h1>Hello, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your lessons, topic requests, exams and assignments.</p>
        <?php if ($calendar !== null): ?><?= $view->partial('term_strip', ['today' => $calendar['today'], 'maneb' => $calendar['maneb']]) ?><?php endif; ?>
        <?php if (!empty($calendar['revision'])): $rv = $calendar['revision']; ?>
            <a class="card section-card revision-banner" href="/revision">
                <strong>MANEB revision is open<?= $rv['days'] > 0 ? ': ' . e($rv['days']) . ' day' . ($rv['days'] === 1 ? '' : 's') . ' to go' : '' ?>.</strong>
                Every Form <?= e(implode(' and Form ', $rv['forms'])) ?> topic is open to you now. See your revision plan →
            </a>
        <?php endif; ?>
        <?php if ($feedback !== []): ?>
            <div class="card section-card recent-feedback">
                <h2 class="h3">Your latest feedback</h2>
                <ul>
                    <?php foreach ($feedback as $f): $fs = \SmartCircle\Application\Assessments\Feedback::status((float) $f['score'], (float) $f['max_score']); [$sym] = \SmartCircle\Application\Assessments\Feedback::STATUSES[$fs]; ?>
                        <li><a href="/assessments/<?= e($f['assessment_id']) ?>/result"><span class="fb fb--<?= e($fs) ?>" title="<?= e(\SmartCircle\Application\Assessments\Feedback::STATUSES[$fs][1]) ?>"><?= \SmartCircle\Presentation\View\Icons::feedback($fs) ?></span>
                            <?= e($f['assessment_title']) ?></a> <span class="muted"><?= e(\SmartCircle\Application\Assessments\AssessmentService::fmt((float) $f['score'])) ?> / <?= e(\SmartCircle\Application\Assessments\AssessmentService::fmt((float) $f['max_score'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($pledge['document_code'])): ?>
            <p class="note">Your pledge, signed <?= e(date('j M Y', (int) strtotime((string) $pledge['accepted_at']))) ?>: <a href="/letters/<?= e($pledge['document_code']) ?>">open and print your certificate</a>.</p>
        <?php endif; ?>
        <div class="tile-grid">
            <a class="tile tile--link" href="/lessons"><h2>Lessons</h2><p>Replay recorded lessons as many times as you need.</p><span class="tag tag--ready">Watch</span></a>
            <a class="tile tile--link" href="/topics"><h2>Topics</h2><p>Stuck on a topic your school has taught? Ask for a lesson on it.</p><span class="tag tag--ready">Ask for help</span></a>
            <a class="tile tile--link" href="/notes"><h2>Notes &amp; slides</h2><p>Read at your own pace; each exercise opens the next part.</p><span class="tag tag--ready">Read</span></a>
            <a class="tile tile--link" href="/assessments"><h2>Exams &amp; assignments</h2><p>Tests on topics your school has taught, with marks and comments.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/grades"><h2>My grades</h2><p>Your term grade in each subject, and your term reports.</p><span class="tag tag--ready">See grades</span></a>
            <a class="tile tile--link" href="/office-hours"><h2>Office hours</h2><p>Book a call with the tutor when you are stuck.</p><span class="tag tag--ready">Book</span></a>
            <?php if (($calendar['form'] ?? 0) >= 2): ?><a class="tile tile--link" href="/results/mine"><h2>MANEB results</h2><p>Share your results when they come out, and help those coming after you.</p><span class="tag tag--ready">Open</span></a><?php endif; ?>
            <?php if (($calendar['form'] ?? 0) >= 3): ?><a class="tile tile--link" href="/alumni/community"><h2>From our alumni</h2><p>Stories and opportunities from students who went before you.</p><span class="tag tag--ready">Read</span></a><?php endif; ?>
            <a class="tile tile--link" href="/messages"><h2>Messages</h2><p>Write to the tutor and read announcements.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/support"><h2>Talk privately</h2><p>Something affecting your studies? A private, kept room with the tutor.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/notifications"><h2>Notifications</h2><p>Find out when a lesson you asked for is planned or ready.</p><span class="tag tag--ready">Open</span></a>
        </div>
    </div>
</section>
