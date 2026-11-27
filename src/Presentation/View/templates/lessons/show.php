<?php
/**
 * Watch a lesson: replay player + the real-life sections.
 * @var array<string, mixed> $lesson
 * @var array<string, string> $subjects
 * @var array<string, string> $sections
 * @var bool $isTutor
 * @var array<string, mixed>|null $topic
 * @var array{name: string, school: ?string}|null $guestCredit
 * @var array<string, mixed>|null $approval
 * @var array<string, mixed>|null $thread
 * @var array{saved: ?array<string, mixed>}|null $ticket
 * @var callable $asset
 */
$icons = ['why_it_matters' => '💡', 'where_in_malawi' => '🌍', 'careers' => '👩🏾‍⚕️', 'try_at_home' => '🏠'];
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/lessons">Lessons</a><?php if ($isTutor): ?> · <a href="/studio/lessons/<?= e($lesson['id']) ?>">Edit</a><?php endif; ?></p>
        <h1 class="lesson-title"><?= e($lesson['title']) ?></h1>
        <button type="button" class="btn btn--small btn--ghost offline-save js-offline-save" hidden
            data-offline-title="<?= e($lesson['title']) ?>"
            data-offline-extra="/lessons/<?= e($lesson['id']) ?>/file /assets/js/board/lesson-file.js /assets/js/board/player.js /assets/js/board/board-core.js"><span class="js-offline-label">Save for offline</span></button>
        <p class="lesson-meta">
            <?php if ($lesson['subject']): ?><span class="badge badge--<?= e($lesson['subject']) ?>"><?= e($subjects[$lesson['subject']] ?? '') ?></span><?php endif; ?>
            <?php if ($lesson['form']): ?><span>Form <?= e($lesson['form']) ?></span><?php endif; ?>
            <?php if ($lesson['status'] !== 'published'): ?><span class="tag">Draft: only you can see this</span><?php endif; ?>
        </p>
        <?php if (!empty($guestCredit)): ?><p class="guest-credit">👩🏽‍🏫 Guest teacher: <strong><?= e($guestCredit['name']) ?></strong><?= $guestCredit['school'] ? ', ' . e($guestCredit['school']) : '' ?></p><?php endif; ?>
        <?= $view->partial('review_badge', ['approval' => $approval ?? null, 'sigBase' => '/reviews/lessons/']) ?>
        <?php if ($topic !== null): ?>
            <p class="note lesson-for">Topic: <a href="/topics/<?= e($topic['id']) ?>"><?= e($topic['title']) ?></a>. For students whose school has taught this topic.</p>
        <?php endif; ?>

        <?= $view->partial('lesson_player', ['src' => '/lessons/' . $lesson['id'] . '/file', 'size' => (int) $lesson['size_bytes']]) ?>

        <?php if (!empty($lesson['summary'])): ?>
            <p class="lead lesson-summary"><?= nl2br(e($lesson['summary'])) ?></p>
        <?php endif; ?>

        <div class="real-life">
            <?php foreach ($sections as $field => $label): ?>
                <?php if (!empty($lesson[$field])): ?>
                    <article class="real-life__card">
                        <h2><span aria-hidden="true"><?= $icons[$field] ?></span> <?= e($label) ?></h2>
                        <p><?= nl2br(e($lesson[$field])) ?></p>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($ticket)): ?><?= $view->partial('exit_ticket', ['lessonId' => (int) $lesson['id'], 'ticket' => $ticket['saved']]) ?><?php endif; ?>
        <?php if (!empty($thread)): ?><?= $view->partial('discussion', ['lessonId' => (int) $lesson['id'], 'thread' => $thread, 'isTutor' => !empty($isTutor)]) ?><?php endif; ?>
    </div>
</section>
<script type="module" src="<?= e($asset('js/board/watch.js')) ?>"></script>
<script src="<?= e($asset('js/offline.js')) ?>" defer></script>
