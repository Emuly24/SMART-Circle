<?php
/**
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var list<string> $pendingUpdates
 * @var array<string, mixed>|null $calendar
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

        <?php if ($calendar !== null): ?><?= $view->partial('term_strip', ['today' => $calendar['today']]) ?><?php endif; ?>
        <div class="tile-grid">
            <a class="tile tile--link" href="/studio/practice"><h2>Try as</h2><p>See every page as a student, teacher, alumnus or team tutor, with practice accounts.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/team"><h2>The Team</h2><p>Invite volunteer tutors for a subject; they agree to our principles first.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/settings"><h2>Settings &amp; backup</h2><p>Your WhatsApp number, the community link, and a database backup.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/students"><h2>Students</h2><p>Activity, gentle early-warning flags, and one-tap call, WhatsApp, SMS or email.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/messages"><h2>Messages</h2><p>Write to students and partner teachers, or send an announcement.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/office-hours"><h2>Office hours</h2><p>Offer call times; students book with what they are stuck on.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/results"><h2>MANEB</h2><p>Who is in revision now, and the results students have shared.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/alumni"><h2>Alumni</h2><p>What former students are doing, the help they need, and mentors.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/year-end"><h2>End of the year</h2><p>Move students up a form, or into the alumni after Form 4.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/support"><h2>Private rooms</h2><p>Kept, private conversations about things affecting a student’s studies.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/requests"><h2>Topic requests</h2><p>Ranked by form and subject, with where students struggle.</p><span class="tag tag--ready">See the ranking</span></a>
            <a class="tile tile--link" href="/studio/checks"><h2>Checks</h2><p>Early-access requests and students’ “my class has covered it” reports.</p><span class="tag tag--ready">Review</span></a>
            <a class="tile tile--link" href="/studio/honesty"><h2>Honesty report</h2><p>Proof that lessons follow what each school has taught.</p><span class="tag tag--ready">Open the report</span></a>
            <a class="tile tile--link" href="/studio/topics"><h2>Topic catalogue</h2><p>The preloaded syllabus: correct, reorder, hide or add topics.</p><span class="tag tag--ready">Edit topics</span></a>
            <a class="tile tile--link" href="/studio/lab"><h2>Recording studio</h2><p>Teach on your iPad board, then upload the lesson from the Replay tab.</p><span class="tag tag--ready">Open the board</span></a>
            <a class="tile tile--link" href="/studio/lessons"><h2>My lessons</h2><p>Add the real-life sections, publish to students, or unpublish.</p><span class="tag tag--ready">Manage lessons</span></a>
            <a class="tile tile--link" href="/studio/admissions"><h2>Admissions</h2><p>Seat chart, applications, entry quizzes, decisions and letters.</p><span class="tag tag--ready">Review applications</span></a>
            <a class="tile tile--link" href="/studio/notes"><h2>Notes &amp; slides</h2><p>Write notes or build slides with checkpoint exercises inside.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/marking"><h2>Marking</h2><p>Everything waiting for your marks, and students’ re-check requests.</p><span class="tag tag--ready">Mark</span></a>
            <a class="tile tile--link" href="/studio/grades"><h2>Grades</h2><p>Gradebook by form, term grades and term reports.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/calendar"><h2>Academic calendar</h2><p>Terms, MANEB dates and every school’s own dates.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/assessments"><h2>Exams &amp; assignments</h2><p>Question bank, timed exams, automatic marking, your marking and results.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/studio/partners"><h2>School partners</h2><p>Invite teachers, see topics covered and textbook pages.</p><span class="tag tag--ready">Partners &amp; schools</span></a>
            <div class="tile"><h2>Mail &amp; templates</h2><p>Branded letters, invitations and the Zoho email queue.</p><span class="tag">Planned</span></div>
        </div>
    </div>
</section>
