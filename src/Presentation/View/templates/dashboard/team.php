<?php
/** @var \SmartCircle\Presentation\Http\Context $ctx */
$user = $ctx->user() ?? [];
$names = \SmartCircle\Support\Subjects::NAMES;
?>
<section class="section">
    <div class="container">
        <p class="eyebrow">SMART Circle Team</p>
        <h1>Welcome, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your subjects: <?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $ctx->subjects() ?? []))) ?>. Everything below shows only these subjects.</p>
        <?php if (!empty($attention)): ?><?= $view->partial('summary_tiles', ['m' => $attention]) ?><?php endif; ?>
        <div class="tile-grid">
            <a class="tile tile--link" href="/studio/lab"><h2>Recording studio</h2><p>Teach on the board, then upload the lesson from the Replay tab.</p><span class="tag tag--ready">Open the board</span></a>
            <a class="tile tile--link" href="/studio/lessons"><h2>My lessons</h2><p>Add the real-life sections and publish to students.</p><span class="tag tag--ready">Manage</span></a>
            <a class="tile tile--link" href="/studio/notes"><h2>Notes &amp; slides</h2><p>Write notes or slides with checkpoint exercises.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/questions"><h2>Question bank</h2><p>Questions for tests, assignments and checkpoints.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/assessments"><h2>Exams &amp; assignments</h2><p>Build and publish them in your subjects.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/marking"><h2>Marking</h2><p>Encouraging marks and feedback, and re-check requests.</p><span class="tag tag--ready">Mark</span></a>
            <a class="tile tile--link" href="/studio/requests"><h2>Topic requests</h2><p>What students in your subjects are asking for.</p><span class="tag tag--ready">See</span></a>
            <a class="tile tile--link" href="/studio/office-hours"><h2>Office hours</h2><p>Offer times when students can book a call.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/messages"><h2>Messages</h2><p>Write to students and the team; announce to students of your subjects.</p><span class="tag tag--ready">Open</span></a>
        </div>
        <?= $view->partial('community') ?>
        <p class="note">Admissions, students’ contact details, private rooms (unless you are added), grades reports and settings are handled by the admin.</p>
    </div>
</section>
