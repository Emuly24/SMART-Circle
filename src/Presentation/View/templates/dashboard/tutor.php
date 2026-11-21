<?php
/**
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var list<string> $pendingUpdates
 */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <p class="eyebrow">Tutor</p>
        <h1>Welcome, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your control centre. Each area below switches on as it is built.</p>

        <?php if ($pendingUpdates !== []): ?>
            <div class="alert alert--info update-banner">
                <p><strong>A database update is ready.</strong> New features need <?= e(count($pendingUpdates)) ?> update<?= count($pendingUpdates) === 1 ? '' : 's' ?> before they work.</p>
                <form method="post" action="/dashboard/database-update">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small">Update database</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="tile-grid">
            <div class="tile"><h2>Topic requests</h2><p>Ranked by form and subject, with where students struggle.</p><span class="tag">Coming next</span></div>
            <a class="tile tile--link" href="/studio/lab"><h2>Recording studio</h2><p>Teach on your iPad board, then upload the lesson from the Replay tab.</p><span class="tag tag--ready">Open the board</span></a>
            <a class="tile tile--link" href="/studio/lessons"><h2>My lessons</h2><p>Add the real-life sections, publish to students, or unpublish.</p><span class="tag tag--ready">Manage lessons</span></a>
            <div class="tile"><h2>Admissions</h2><p>Seat chart, applications, entry quizzes and letters.</p><span class="tag">Coming next</span></div>
            <div class="tile"><h2>Exams &amp; assignments</h2><p>Question bank, auto-marking and Pencil marking.</p><span class="tag">Planned</span></div>
            <div class="tile"><h2>School partners</h2><p>Invite teachers, see topics covered and textbook pages.</p><span class="tag">Planned</span></div>
            <div class="tile"><h2>Mail &amp; templates</h2><p>Branded letters, invitations and the Zoho email queue.</p><span class="tag">Planned</span></div>
        </div>
    </div>
</section>
