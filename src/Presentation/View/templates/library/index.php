<?php
/**
 * @var array{form: ?int, subject: ?string} $filters
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $notes
 * @var list<array<string, mixed>> $handouts
 */
$kb = static fn (int $bytes): string => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
?>
<section class="section section--tight">
    <div class="container">
        <h1>Free library</h1>
        <p class="lead">Handouts from SMART Circle lessons, free for every student, parent and teacher. No account needed. Read them here, print them or save them as PDF.</p>

        <form method="get" action="/library" class="filters library-filters">
            <label>Form
                <select name="form">
                    <option value="">All forms</option>
                    <?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= e($f) ?>"<?= $filters['form'] === $f ? ' selected' : '' ?>>Form <?= e($f) ?></option><?php endfor; ?>
                </select></label>
            <label>Subject
                <select name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($names as $key => $name): ?><option value="<?= e($key) ?>"<?= $filters['subject'] === $key ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select></label>
            <button type="submit" class="btn btn--small">Show</button>
        </form>

        <?php if ($notes === [] && $handouts === []): ?>
            <div class="card"><p>No handouts here yet<?= $filters['form'] !== null || $filters['subject'] !== null ? ' for this choice. Try all forms or all subjects' : '. New ones are added as lessons are recorded' ?>.</p></div>
        <?php endif; ?>

        <?php if ($notes !== []): ?>
            <h2 class="library-heading">Notes</h2>
            <div class="lesson-grid">
                <?php foreach ($notes as $n): ?>
                    <a class="lesson-card library-card" href="/library/notes/<?= e($n['id']) ?>">
                        <span class="lesson-card__meta"><span class="badge badge--<?= e($n['subject']) ?>"><?= e($names[$n['subject']] ?? '') ?></span>
                            <span class="lesson-card__form">Form <?= e($n['form']) ?> · <?= $n['kind'] === 'slides' ? 'Slides' : 'Notes' ?></span></span>
                        <span class="lesson-card__title"><?= e($n['title']) ?></span>
                        <span class="lesson-card__info"><?= e($n['topic_title']) ?></span>
                        <?php if (($n['summary'] ?? '') !== ''): ?><span class="lesson-card__info"><?= e($n['summary']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($handouts !== []): ?>
            <h2 class="library-heading">PDF handouts</h2>
            <div class="lesson-grid">
                <?php foreach ($handouts as $h): ?>
                    <a class="lesson-card library-card" href="/library/lessons/<?= e($h['id']) ?>/handout">
                        <span class="lesson-card__meta"><span class="badge badge--<?= e($h['subject']) ?>"><?= e($names[$h['subject']] ?? '') ?></span>
                            <span class="lesson-card__form">Form <?= e($h['form']) ?> · PDF, <?= e($kb((int) $h['handout_bytes'])) ?></span></span>
                        <span class="lesson-card__title"><?= e($h['title']) ?></span>
                        <?php if (($h['topic_title'] ?? '') !== ''): ?><span class="lesson-card__info"><?= e($h['topic_title']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $view->partial('share_buttons', ['shareUrl' => $shareLink('/library'), 'shareText' => 'Free printable handouts for Form 1–4 students in Malawi, from SMART Circle. No account needed.', 'label' => 'Share the free library']) ?>
        <?= $view->partial('library_invite') ?>
    </div>
</section>
