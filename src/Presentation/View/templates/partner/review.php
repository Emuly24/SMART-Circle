<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
use SmartCircle\Presentation\View\NoteMarkup;

$n = $r['note'];
$id = (int) $n['id'];
$me = $r['me'];
$state = $r['state']['state'];
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$cp = 0;
?>
<section class="section section--tight">
    <div class="container reader">
        <p class="crumbs"><a href="/partner/reviews">Check notes</a></p>
        <p><span class="badge badge--<?= e($n['subject']) ?>"><?= e($names[$n['subject']] ?? '') ?></span> <span class="muted">Form <?= e($n['form']) ?> · <?= e($n['topic_title']) ?></span></p>
        <h1><?= e($n['title']) ?></h1>
        <?php if ($state === 'approved'): ?><div class="alert alert--success">✓ Approved as it is now<?= $r['state']['review']['reviewer_id'] !== null && (int) $r['state']['review']['reviewer_id'] === (int) $me['id'] ? ' by you' : ' by ' . e($r['state']['review']['reviewer_name']) ?>.</div>
        <?php elseif ($state === 'stale'): ?><div class="alert alert--info">These notes were approved, then changed. Please check them again.</div><?php endif; ?>
        <?php if (($n['summary'] ?? '') !== ''): ?><p class="lead"><?= e($n['summary']) ?></p><?php endif; ?>

        <?php foreach ($r['blocks'] as $b): $bid = (int) $b['id']; ?>
            <?php if ($b['type'] === 'text'): ?>
                <div class="note-prose"><?= NoteMarkup::render((string) $b['body']) ?></div>
            <?php elseif ($b['type'] === 'image'): ?>
                <figure class="note-figure note-figure--<?= e($b['align'] ?? 'center') ?>"><img src="/partner/reviews/<?= e($id) ?>/images/<?= e($bid) ?>" alt="<?= e($b['body'] ?? 'Picture') ?>" loading="lazy">
                    <?php if ($b['body']): ?><figcaption><?= e($b['body']) ?></figcaption><?php endif; ?></figure>
            <?php else: $cp++; ?>
                <div class="card checkpoint review-exercise">
                    <p class="checkpoint__title"><?= $b['is_final'] ? '🏁 End of unit exercise' : '✏️ Checkpoint ' . $cp ?></p>
                    <?php if ($b['questions'] === []): ?><p class="muted">No questions yet.</p><?php endif; ?>
                    <ol class="review-questions">
                        <?php foreach ($b['questions'] as $q): ?>
                            <li><div class="note-prose"><?= NoteMarkup::render((string) $q['prompt']) ?></div>
                                <?php if ($q['options'] !== []): ?><ul class="review-options"><?php foreach ($q['options'] as $o): ?><li><?= NoteMarkup::inline((string) $o) ?></li><?php endforeach; ?></ul><?php endif; ?>
                                <p class="review-answer"><?= $q['answer'] !== '' ? 'Answer: <strong>' . NoteMarkup::inline($q['answer']) . '</strong>' : '<span class="muted">Written answer, marked by the tutor</span>' ?> · <?= e($q['marks']) ?> mark<?= (int) $q['marks'] === 1 ? '' : 's' ?></p></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="card review-decision" id="decide">
            <h2>Your decision</h2>
            <?php if ($me['signature_path'] === null): ?>
                <div class="alert alert--info">To approve, first <a href="/partner/signature?back=/partner/reviews/<?= e($id) ?>">add your signature</a>. You can suggest changes without it.</div>
            <?php endif; ?>
            <form method="post" action="/partner/reviews/<?= e($id) ?>" class="form"><?= $csrf->field() ?>
                <fieldset class="field"><legend class="field__label">Are these notes and exercises accurate for the syllabus?</legend>
                    <label class="choice choice--block"><input type="radio" name="decision" value="approved" <?= ($old['decision'] ?? '') === 'approved' ? 'checked' : '' ?> <?= $me['signature_path'] === null ? 'disabled' : '' ?>> Yes, approve them with my signature</label>
                    <label class="choice choice--block"><input type="radio" name="decision" value="changes" <?= ($old['decision'] ?? '') === 'changes' ? 'checked' : '' ?>> They need changes</label>
                    <?= $err('decision') ?></fieldset>
                <label class="field"><span class="field__label">Comments for the tutor <span class="muted">(needed for changes)</span></span>
                    <textarea name="comment" rows="5" maxlength="3000" placeholder="e.g. In the second example the unit should be N, not kg."><?= e($old['comment'] ?? '') ?></textarea><?= $err('comment') ?></label>
                <?php if ($me['signature_path'] !== null): ?>
                    <p class="muted">Approving shows: <strong><?= e($me['full_name']) ?></strong><?= $me['school_name'] ? ', ' . e($me['school_name']) : '' ?>, today's date and your signature.</p>
                <?php endif; ?>
                <div class="form-actions"><button type="submit" class="btn btn--green">Send my decision</button></div>
            </form>
        </div>

        <?php if ($r['history'] !== []): ?>
            <h2>Earlier checks</h2>
            <?= $view->partial('review_history', ['history' => $r['history']]) ?>
        <?php endif; ?>
    </div>
</section>
