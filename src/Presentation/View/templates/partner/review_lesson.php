<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var array<string, string> $names
 * @var array<string, string> $sections
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$l = $r['lesson'];
$id = (int) $l['id'];
$me = $r['me'];
$state = $r['state']['state'];
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container reader">
        <p class="crumbs"><a href="/partner/reviews">Check notes and lessons</a></p>
        <p><?php if ($l['subject']): ?><span class="badge badge--<?= e($l['subject']) ?>"><?= e($names[$l['subject']] ?? '') ?></span><?php endif; ?> <span class="muted">Form <?= e($l['form']) ?><?= $l['topic_title'] ? ' · ' . e($l['topic_title']) : '' ?></span></p>
        <h1><?= e($l['title']) ?></h1>
        <?php if ($state === 'approved'): ?><div class="alert alert--success">✓ Approved as it is now by <?= e($r['state']['review']['reviewer_name']) ?>.</div>
        <?php elseif ($state === 'stale'): ?><div class="alert alert--info">This lesson was approved, then changed. Please check it again.</div><?php endif; ?>
        <?= $view->partial('lesson_player', ['src' => '/partner/reviews/lessons/' . $id . '/file', 'size' => (int) $l['size_bytes']]) ?>
        <?php if (!empty($l['summary'])): ?><p class="lead"><?= nl2br(e($l['summary'])) ?></p><?php endif; ?>
        <div class="real-life">
            <?php foreach ($sections as $field => $label): ?><?php if (!empty($l[$field])): ?>
                <article class="real-life__card"><h2><?= e($label) ?></h2><p><?= nl2br(e($l[$field])) ?></p></article>
            <?php endif; ?><?php endforeach; ?>
        </div>
        <?php if (!empty($l['handout_path'])): ?><p><a class="btn btn--small btn--ghost" href="/partner/reviews/lessons/<?= e($id) ?>/handout" target="_blank" rel="noopener">Open the PDF handout</a></p><?php endif; ?>

        <div class="card review-decision" id="decide">
            <h2>Your decision</h2>
            <?php if ($me['signature_path'] === null): ?><div class="alert alert--info">To approve, first <a href="/partner/signature">add your signature</a>. You can suggest changes without it.</div><?php endif; ?>
            <form method="post" action="/partner/reviews/lessons/<?= e($id) ?>" class="form"><?= $csrf->field() ?>
                <fieldset class="field"><legend class="field__label">Is this lesson accurate for the syllabus<?= !empty($l['handout_path']) ? ', with its handout' : '' ?>?</legend>
                    <label class="choice choice--block"><input type="radio" name="decision" value="approved" <?= ($old['decision'] ?? '') === 'approved' ? 'checked' : '' ?> <?= $me['signature_path'] === null ? 'disabled' : '' ?>> Yes, approve it with my signature</label>
                    <label class="choice choice--block"><input type="radio" name="decision" value="changes" <?= ($old['decision'] ?? '') === 'changes' ? 'checked' : '' ?>> It needs changes</label>
                    <?= $err('decision') ?></fieldset>
                <label class="field"><span class="field__label">Comments for the tutor <span class="muted">(needed for changes; say the minute if you can)</span></span>
                    <textarea name="comment" rows="5" maxlength="3000"><?= e($old['comment'] ?? '') ?></textarea><?= $err('comment') ?></label>
                <div class="form-actions"><button type="submit" class="btn btn--green">Send my decision</button></div>
            </form>
        </div>
        <?php if ($r['history'] !== []): ?><h2>Earlier checks</h2><?= $view->partial('review_history', ['history' => $r['history']]) ?><?php endif; ?>
    </div>
</section>
<script type="module" src="<?= e($asset('js/board/watch.js')) ?>"></script>
