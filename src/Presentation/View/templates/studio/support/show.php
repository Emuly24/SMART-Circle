<?php
/**
 * @var array<string, mixed> $t
 * @var array<string, mixed>|null $student
 * @var string|null $guardianLink
 * @var int $me
 * @var string $draft
 * @var string|null $error
 */
$c = $t['conversation'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/support">Private rooms</a><?php if ($student !== null): ?> · <a href="/studio/students/<?= e($student['id']) ?>"><?= e($student['full_name']) ?></a><?php endif; ?></p>
        <h1>Private room · <?= e($c['student_name']) ?></h1>
        <?php if ($c['opened_by'] === 'tutor'): ?>
            <div class="card section-card guardian-card">
                <?php if ($c['guardian_notified_at'] !== null): ?>
                    <p>Guardian told about the invitation on <?= e(date('j M Y', (int) strtotime((string) $c['guardian_notified_at']))) ?>.</p>
                <?php else: ?>
                    <p><strong>Let the guardian know</strong> that you invited <?= e($c['student_name']) ?> to talk privately (not what it is about).</p>
                    <div class="contact-buttons">
                        <?php if ($guardianLink !== null): ?><a class="btn btn--small btn--green" href="<?= e($guardianLink) ?>" target="_blank" rel="noopener">WhatsApp the guardian</a><?php else: ?><span class="muted">No guardian phone number on file.</span><?php endif; ?>
                        <form method="post" action="/studio/support/<?= e($c['id']) ?>/guardian" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">I have told the guardian</button></form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?= $view->partial('thread', ['t' => $t, 'me' => $me]) ?>
        <?= $view->partial('reply_form', ['action' => '/studio/support/' . $c['id'], 'draft' => $draft, 'error' => $error, 'label' => 'Reply']) ?>
        <details class="card section-card"><summary class="h3">The rules students see</summary><?= $view->partial('room_rules', ['forTutor' => true]) ?></details>
    </div>
</section>
