<?php
/**
 * The home page for someone who is signed in: made for their role.
 * @var array<string, mixed> $m role, user, unread, to_mark, rechecks, subjects, revision, form
 */
$first = explode(' ', (string) ($m['user']['full_name'] ?? ''))[0];
$roleNames = ['tutor' => 'Admin', 'team' => 'SMART Circle Team', 'student' => 'Student', 'partner' => 'Partner teacher', 'alumni' => 'Alumni'];
$names = \SmartCircle\Support\Subjects::NAMES;
$links = match ($m['role']) {
    'tutor' => [
        ['/dashboard', 'Dashboard', 'Everything in one place'], ['/studio/students', 'Students', 'Activity, flags and contacts'],
        ['/studio/marking', 'Marking', 'Work waiting for you'], ['/studio/admissions', 'Admissions', 'Applications and seats'],
        ['/studio/team', 'The Team', 'Invite and manage tutors'], ['/studio/practice', 'Try as', 'See every page as anyone'],
        ['/studio/contributions', 'From teachers', 'Calendars, resources and topic suggestions'],
    ],
    'team' => [
        ['/studio/lab', 'Recording studio', 'Teach on the board'], ['/studio/lessons', 'My lessons', 'Publish and edit'],
        ['/studio/notes', 'Notes & slides', 'Write with checkpoints'], ['/studio/marking', 'Marking', 'Work in your subjects'],
        ['/studio/requests', 'Topic requests', 'What students need'], ['/messages', 'Messages', 'Students and the team'],
    ],
    'partner' => [
        ['/dashboard', 'Dashboard', 'Your school at a glance'], ['/partner/coverage', 'Topics taught', 'Keep it up to date'],
        ['/partner/calendar', 'Calendar', 'Your school’s dates'], ['/partner/textbooks', 'Textbooks', 'Books your school uses'],
        ['/partner/contribute', 'Contribute', 'Confirm the calendar, share resources, suggest topics'],
    ],
    'alumni' => [
        ['/alumni', 'What I am doing', 'Tell us, ask for help'], ['/alumni/community', 'Community', 'Stories and opportunities'],
        ['/results/mine', 'MANEB results', 'Share if you wish'], ['/office-hours', 'Office hours', 'Book a call'],
    ],
    default => [
        ['/lessons', 'Lessons', 'Replay any time'], ['/topics', 'Topics', 'Ask for help'], ['/notes', 'Notes & slides', 'Read at your pace'],
        ['/assessments', 'Exams', 'Tests and assignments'], ['/quick5', 'Quick 5', 'Five questions a week'], ['/support', 'Talk privately', 'A private room with the tutor'], ['/office-hours', 'Office hours', 'Book a call'],
    ],
};
?>
<section class="hero hero--member">
    <div class="container">
        <p class="eyebrow"><?= e($roleNames[$m['role']] ?? '') ?></p>
        <h1 class="reveal">Hello, <?= e($first) ?></h1>
        <?php if ($m['role'] === 'team' && $m['subjects'] !== null): ?>
            <p class="lead">You teach <?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $m['subjects']))) ?>. Thank you for giving your time.</p>
        <?php elseif ($m['role'] === 'tutor'): ?>
            <p class="lead">Here is what needs you today.</p>
        <?php else: ?>
            <p class="lead">Good to see you. Pick up where you left off.</p>
        <?php endif; ?>
        <div class="stat-tiles">
            <a class="stat-tile reveal" href="/messages"><span class="stat-tile__n"><?= e($m['unread']) ?></span><span>unread conversation<?= $m['unread'] === 1 ? '' : 's' ?></span></a>
            <?php if (in_array($m['role'], ['tutor', 'team'], true)): ?>
                <a class="stat-tile reveal" href="/studio/marking"><span class="stat-tile__n"><?= e($m['to_mark']) ?></span><span>to mark</span></a>
            <?php endif; ?>
            <?php if ($m['role'] === 'tutor'): ?>
                <a class="stat-tile reveal" href="/studio/rechecks"><span class="stat-tile__n"><?= e($m['rechecks']) ?></span><span>re-check request<?= $m['rechecks'] === 1 ? '' : 's' ?></span></a>
            <?php endif; ?>
            <?php if (!empty($m['revision'])): ?>
                <a class="stat-tile stat-tile--amber reveal" href="/revision"><span class="stat-tile__n"><?= e($m['revision']['days']) ?></span><span>days to MANEB · revision open</span></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<section class="section section--tight">
    <div class="container">
        <div class="tile-grid">
            <?php foreach ($links as [$href, $label, $blurb]): ?>
                <a class="tile tile--link reveal" href="<?= e($href) ?>"><h2><?= e($label) ?></h2><p><?= e($blurb) ?></p></a>
            <?php endforeach; ?>
        </div>
        <?php if ($m['role'] !== 'tutor'): ?><?= $view->partial('community') ?><?php endif; ?>
    </div>
</section>
