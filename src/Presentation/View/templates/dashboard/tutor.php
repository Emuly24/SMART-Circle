<?php
/** @var \SmartCircle\Presentation\Http\Context $ctx */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <p class="eyebrow">Tutor</p>
        <h1>Welcome, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your control centre. Each area below switches on as it is built.</p>

        <div class="tile-grid">
            <div class="tile"><h2>Topic requests</h2><p>Ranked by form and subject, with where students struggle.</p><span class="tag">Coming next</span></div>
            <div class="tile"><h2>Recording studio</h2><p>Teach on your iPad board; lessons become replays and free handouts.</p><span class="tag">In progress</span></div>
            <div class="tile"><h2>Admissions</h2><p>Seat chart, applications, entry quizzes and letters.</p><span class="tag">Coming next</span></div>
            <div class="tile"><h2>Exams &amp; assignments</h2><p>Question bank, auto-marking and Pencil marking.</p><span class="tag">Planned</span></div>
            <div class="tile"><h2>School partners</h2><p>Invite teachers, see topics covered and textbook pages.</p><span class="tag">Planned</span></div>
            <div class="tile"><h2>Mail &amp; templates</h2><p>Branded letters, invitations and the Zoho email queue.</p><span class="tag">Planned</span></div>
        </div>
    </div>
</section>
