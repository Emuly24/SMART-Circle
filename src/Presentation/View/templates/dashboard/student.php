<?php
/**
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var array<string, mixed>|null $pledge
 */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <h1>Hello, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your lessons, topic requests, exams and assignments.</p>
        <?php if (!empty($pledge['document_code'])): ?>
            <p class="note">Your pledge, signed <?= e(date('j M Y', (int) strtotime((string) $pledge['accepted_at']))) ?>: <a href="/letters/<?= e($pledge['document_code']) ?>">open and print your certificate</a>.</p>
        <?php endif; ?>
        <div class="tile-grid">
            <a class="tile tile--link" href="/lessons"><h2>Lessons</h2><p>Replay recorded lessons as many times as you need.</p><span class="tag tag--ready">Watch</span></a>
            <a class="tile tile--link" href="/topics"><h2>Topics</h2><p>Stuck on a topic your school has taught? Ask for a lesson on it.</p><span class="tag tag--ready">Ask for help</span></a>
            <a class="tile tile--link" href="/assessments"><h2>Exams &amp; assignments</h2><p>Tests on topics your school has taught, with marks and comments.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/notifications"><h2>Notifications</h2><p>Find out when a lesson you asked for is planned or ready.</p><span class="tag tag--ready">Open</span></a>
        </div>
    </div>
</section>
