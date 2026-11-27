<?php
/**
 * @var list<array<string, mixed>> $reports
 * @var list<array<string, mixed>> $pending
 * @var list<array<string, mixed>> $challenges
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $topicOptions
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$btn = static fn (string $action, string $label, string $class = 'btn btn--small btn--ghost', string $extra = ''): string =>
    '<form method="post" action="' . e($action) . '" class="inline-form">' . $csrf->field() . $extra . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Community</h1>
        <p class="lead">Keep the community kind and safe: reported comments, wall photos waiting for you, and weekly challenges. <a href="/community/wall">See the wall</a>.</p>

        <h2>Reported (<?= e(count($reports)) ?>)</h2>
        <?php if ($reports === []): ?><div class="card"><p>Nothing reported. 🎉</p></div><?php endif; ?>
        <?php foreach ($reports as $r): ?>
            <div class="card suggestion">
                <p class="muted">Reported by <?= e($r['reporter']) ?> · <?= e(date('j M, H:i', (int) strtotime((string) $r['created_at']))) ?>: “<?= e($r['reason']) ?>”</p>
                <?php if ($r['target_type'] === 'comment'): ?>
                    <p><strong><?= e($r['author'] ?? '') ?>:</strong> <?= e($r['comment_body'] ?? '') ?></p>
                    <div class="decision__buttons">
                        <?= $btn('/comments/' . $r['target_id'] . '/hide', 'Hide the comment', 'btn btn--small btn--danger', '<input type="hidden" name="back" value="community">') ?>
                        <?= $btn('/studio/community/reports/' . $r['id'] . '/keep', 'It is fine: keep it') ?>
                        <?php if ($r['on_type'] === 'lesson'): ?><a class="btn btn--small btn--ghost" href="/lessons/<?= e($r['on_id']) ?>#comment-<?= e($r['target_id']) ?>">See it in the lesson</a><?php endif; ?>
                    </div>
                <?php else: ?>
                    <p><img class="moderation-photo" src="/community/wall/<?= e($r['target_id']) ?>/photo" alt=""> <strong><?= e($r['wall_author'] ?? '') ?>:</strong> <?= e($r['wall_caption'] ?? '') ?></p>
                    <div class="decision__buttons"><?= $btn('/studio/community/wall/' . $r['target_id'] . '/hide', 'Hide the photo', 'btn btn--small btn--danger') ?><?= $btn('/studio/community/reports/' . $r['id'] . '/keep', 'It is fine: keep it') ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <h2 id="wall">Wall photos waiting (<?= e(count($pending)) ?>)</h2>
        <?php if ($pending === []): ?><div class="card"><p>No photos waiting.</p></div><?php else: ?>
            <ul class="wall-grid"><?php foreach ($pending as $p): ?>
                <li><figure class="wall-card"><img src="/community/wall/<?= e($p['id']) ?>/photo" alt="<?= e($p['caption']) ?>"><figcaption><?= e($p['caption']) ?><br><span class="muted"><?= e($p['full_name']) ?><?= $p['form'] ? ', Form ' . e($p['form']) : '' ?></span>
                    <div class="decision__buttons"><?= $btn('/studio/community/wall/' . $p['id'] . '/approve', 'Show on the wall', 'btn btn--small btn--green') ?><?= $btn('/studio/community/wall/' . $p['id'] . '/feature', 'Feature it') ?><?= $btn('/studio/community/wall/' . $p['id'] . '/hide', 'Do not show') ?></div>
                </figcaption></figure></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>

        <h2 id="challenges">Weekly challenges</h2>
        <form method="post" action="/studio/community/challenges" class="form card"><?= $csrf->field() ?>
            <h3 class="h4">New challenge</h3>
            <div class="field-row">
                <label class="field"><span class="field__label">Form</span><select name="form"><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (int) ($old['form'] ?? 0) === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('form') ?></label>
                <label class="field"><span class="field__label">Subject</span><select name="subject"><?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= ($old['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('subject') ?></label>
                <label class="field"><span class="field__label">Open for (days)</span><input type="number" name="days" min="1" max="21" value="<?= e($old['days'] ?? 7) ?>"><?= $err('days') ?></label>
            </div>
            <label class="field"><span class="field__label">Topic <span class="muted">(only students whose school has taught it see the challenge)</span></span><select name="topic_id"><option value="0">No topic: everyone in the form</option>
                <?php foreach ($topicOptions as $o): ?><option value="<?= e($o['id']) ?>" <?= (int) ($old['topic_id'] ?? 0) === $o['id'] ? 'selected' : '' ?>>Form <?= e($o['form']) ?> · <?= e($names[$o['subject']] ?? '') ?> · <?= e($o['label']) ?></option><?php endforeach; ?></select><?= $err('topic_id') ?></label>
            <label class="field"><span class="field__label">Title</span><input type="text" name="title" maxlength="200" value="<?= e($old['title'] ?? '') ?>" required><?= $err('title') ?></label>
            <label class="field"><span class="field__label">The real-life problem</span><textarea name="body" rows="5" required placeholder="e.g. A farmer in Kasungu has 2 bags of fertiliser..."><?= e($old['body'] ?? '') ?></textarea><?= $err('body') ?></label>
            <div class="form-actions"><button type="submit" class="btn btn--small btn--green">Post the challenge</button></div>
        </form>
        <?php if ($challenges !== []): ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Challenge</th><th>Answers</th><th>Closes</th><th></th></tr></thead><tbody>
                <?php foreach ($challenges as $c): ?>
                    <tr><td><a href="/community/challenges/<?= e($c['id']) ?>"><?= e($c['title']) ?></a><br><span class="muted">Form <?= e($c['form']) ?> · <?= e($names[$c['subject']] ?? '') ?><?= empty($c['explanation']) ? ' · no explanation yet' : '' ?></span></td>
                        <td><?= e($c['answers']) ?></td><td><?= e(date('j M', (int) strtotime((string) $c['closes_at']))) ?></td>
                        <td><form method="post" action="/studio/community/challenges/<?= e($c['id']) ?>/delete" class="inline-form js-confirm" data-confirm="Delete this challenge and its answers?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Delete</button></form></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
