<?php
/**
 * @var \SmartCircle\Presentation\Http\Context $ctx
 * @var array<string, mixed>|null $summary
 * @var array<string, mixed>|null $calendar
 */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="eyebrow">School partner</p>
        <h1>Welcome, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <?php if ($calendar !== null): ?><?= $view->partial('term_strip', ['today' => $calendar['today']]) ?><?php endif; ?>
        <?php if ($summary === null): ?>
            <p class="lead">Your account is not linked to a school yet. Please contact the tutor.</p>
        <?php else:
            $p = $summary['partner'];
            $total = array_sum($summary['students']); ?>
            <p class="lead">
                Thank you for supporting <?= e($p['school_name']) ?>’s students.
                <?= e($total) ?> of your students <?= $total === 1 ? 'is' : 'are' ?> in SMART Circle.
                Lessons follow what your school has taught, so your reports keep everyone on the same page.
            </p>
            <?= $view->partial('school_overview', [
                'scope' => ['id' => (int) $p['school_id'], 'school' => ['name' => $p['school_name']], 'subjects' => $p['subject_list'], 'base' => '/partner', 'isTutor' => false],
                'progress' => $summary['progress'],
                'top' => $summary['top'],
                'bookCount' => $summary['books'],
                'fresh' => $summary['fresh'],
                'names' => \SmartCircle\Support\Subjects::NAMES,
            ]) ?>
            <?php if ($summary['letters'] !== []): ?>
                <p class="note">Your invitation letter: <a href="/letters/<?= e($summary['letters'][0]['code']) ?>">open and print</a>.</p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($ctx->isGuestTeacher()): ?>
            <a class="card section-card community-card" href="/studio/lab"><span><strong>Record a lesson</strong><br><span class="muted">You are a guest teacher. Record on the board, then send the lesson to the tutor, who publishes it with your name. <span class="nowrap">See <u>My lessons</u>.</span></span></span></a>
        <?php endif; ?>
        <a class="card section-card community-card" href="/partner/questions">
            <span><strong>Suggest questions</strong><br><span class="muted">Exercise and exam questions in your subjects. Good ones join the question bank with your name.</span></span>
        </a>
        <a class="card section-card community-card" href="/partner/reviews">
            <span><strong>Check notes and sign them</strong><br><span class="muted">Read the tutor’s notes in your subjects. Approve them with your signature, or suggest changes.</span></span>
        </a>
        <a class="card section-card community-card" href="/partner/contribute">
            <span><strong>Help keep SMART Circle accurate</strong><br><span class="muted">Confirm your school’s calendar, share your syllabus or scheme of work, and suggest missing topics.</span></span>
        </a>
        <?= $view->partial('community') ?>
    </div>
</section>
