<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var array<string, mixed>|null $approval
 */
use SmartCircle\Presentation\View\NoteMarkup;

$n = $r['note'];
$fmt = static fn ($x): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $x);
?>
<section class="section section--tight">
    <div class="container reader">
        <p class="crumbs"><a href="/notes">Notes &amp; slides</a></p>
        <p><span class="badge badge--<?= e($n['subject']) ?>"><?= e($names[$n['subject']] ?? '') ?></span> <span class="muted"><?= e($n['topic_title']) ?></span></p>
        <h1><?= e($n['title']) ?></h1>
        <?php if (!$r['locked']): ?><button type="button" class="btn btn--small btn--ghost offline-save js-offline-save" hidden data-offline-title="<?= e($n['title']) ?>"><span class="js-offline-label">Save for offline</span></button><?php endif; ?>
        <?php if ($r['locked']): ?>
            <div class="card locked-card"><p class="locked-card__icon" aria-hidden="true">🔒</p>
                <p class="lead">These notes open when your school has taught <strong><?= e($n['topic_title']) ?></strong>.</p>
                <p><a class="btn" href="/topics/<?= e($n['topic_id']) ?>">See the topic and your options</a></p></div>
        <?php else: ?>
            <?php if ($r['total_checkpoints'] > 0): ?>
                <p class="reader__progress"><?= e($r['done']) ?> of <?= e($r['total_checkpoints']) ?> exercises done</p>
            <?php endif; ?>
            <?php foreach ($r['blocks'] as $b): $bid = (int) $b['id']; ?>
                <?php if ($b['type'] === 'text'): ?>
                    <div class="note-prose" id="block-<?= e($bid) ?>"><?= NoteMarkup::render((string) $b['body']) ?></div>
                <?php elseif ($b['type'] === 'image'): ?>
                    <figure class="note-figure note-figure--<?= e($b['align'] ?? 'center') ?>" id="block-<?= e($bid) ?>"><img src="/notes/<?= e($n['id']) ?>/images/<?= e($bid) ?>" alt="<?= e($b['body'] ?? 'Slide') ?>" loading="lazy">
                        <?php if ($b['body']): ?><figcaption><?= e($b['body']) ?></figcaption><?php endif; ?></figure>
                <?php elseif ($b['state'] === 'submitted'):
                    $cp = $b['view']; $sub = $cp['submission']; ?>
                    <div class="card checkpoint checkpoint--done" id="block-<?= e($bid) ?>">
                        <p class="checkpoint__title">✓ <?= $b['is_final'] ? 'End of unit exercise' : 'Checkpoint' ?> done</p>
                        <?php if ($cp['state'] === 'results'): ?>
                            <p>Score: <strong><?= e($fmt($sub['score'])) ?> / <?= e($fmt($sub['max_score'])) ?></strong> · <a href="/assessments/<?= e($b['assessment_id']) ?>/result">See your feedback</a></p>
                        <?php else: ?>
                            <p class="muted">Submitted. The tutor will mark your written answers; your feedback will appear on your dashboard.</p>
                        <?php endif; ?>
                    </div>
                <?php else: $at = $b['attempt']; ?>
                    <div class="card checkpoint checkpoint--active" id="block-<?= e($bid) ?>">
                        <p class="checkpoint__title"><?= $b['is_final'] ? '🏁 End of unit exercise' : '✏️ Checkpoint' ?></p>
                        <p class="muted">Answer these to open the next part. Do your best: your work will be marked with feedback to help you.</p>
                        <?php if ($at !== null): ?>
                            <form method="post" action="/notes/<?= e($n['id']) ?>/checkpoint/<?= e($bid) ?>" class="form exam-form js-confirm" data-confirm="Submit your answers? You cannot change them afterwards.">
                                <?= $csrf->field() ?>
                                <?= $view->partial('question_fields', ['questions' => $at['questions'], 'answers' => $at['answers']]) ?>
                                <div class="form-actions"><button type="submit" class="btn btn--green">Submit and continue</button></div>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if ($r['hidden'] > 0): ?>
                        <p class="reader__locked">🔒 <?= e($r['hidden']) ?> more part<?= $r['hidden'] === 1 ? '' : 's' ?> will open after this exercise.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($r['hidden'] === 0): ?><?= $view->partial('review_badge', ['approval' => $approval ?? null]) ?><?php endif; ?>
            <?php if ($r['hidden'] === 0 && $r['total_checkpoints'] === $r['done']): ?>
                <div class="card reader__end"><p>🎉 You have finished these notes. Well done!</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
<script src="<?= e($asset('js/offline.js')) ?>" defer></script>
