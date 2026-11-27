<?php
/**
 * Questions and comments under a lesson.
 * @var int $lessonId
 * @var array{comments: list<array<string, mixed>>, mine: list<string>, following: bool} $thread
 * @var bool $isTutor
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$back = '/lessons/' . $lessonId;
$time = static fn (?int $ms): string => $ms === null ? '' : sprintf('%d:%02d', intdiv($ms, 60000), intdiv($ms % 60000, 1000));
$item = null;
$item = static function (array $c, bool $reply) use (&$item, $csrf, $back, $thread, $isTutor, $time, $lessonId): string {
    $id = (int) $c['id'];
    $hidden = $c['status'] === 'hidden';
    $h = '<li class="comment' . ($reply ? ' comment--reply' : '') . ($hidden ? ' comment--hidden' : '') . ($c['is_approved_answer'] ? ' comment--approved' : '') . '" id="comment-' . e($id) . '">'
        . '<p class="comment__head"><strong>' . e($c['author']) . '</strong> <span class="muted">' . e(date('j M, H:i', (int) strtotime((string) $c['created_at']))) . '</span>'
        . ($c['lesson_time_ms'] !== null ? ' <span class="chip">at ' . e($time((int) $c['lesson_time_ms'])) . '</span>' : '')
        . ($c['is_approved_answer'] ? ' <span class="chip chip--lesson">✓ Tutor approved</span>' : '')
        . ($hidden ? ' <span class="chip">Hidden from students</span>' : '') . '</p>'
        . '<p class="comment__body">' . $c['html'] . '</p><div class="comment__actions">';
    foreach (\SmartCircle\Application\Community\DiscussionService::REACTIONS as $kind => $label) {
        if ($kind === 'same_question' && $reply) {
            continue;
        }
        $on = in_array($id . ':' . $kind, $thread['mine'], true);
        $n = (int) ($kind === 'same_question' ? $c['same_count'] : $c['thanks_count']);
        $h .= '<form method="post" action="/comments/' . e($id) . '/react" class="inline-form">' . $csrf->field()
            . '<input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="back" value="' . e($back) . '">'
            . '<button type="submit" class="react' . ($on ? ' react--on' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">' . e($label) . ($n > 0 ? ' · ' . e($n) : '') . '</button></form>';
    }
    if ($isTutor) {
        $h .= '<form method="post" action="/comments/' . e($id) . '/' . ($hidden ? 'show' : 'hide') . '" class="inline-form">' . $csrf->field() . '<button type="submit" class="react">' . ($hidden ? 'Show' : 'Hide') . '</button></form>';
        if ($reply) {
            $h .= '<form method="post" action="/comments/' . e($id) . '/' . ($c['is_approved_answer'] ? 'unapprove' : 'approve') . '" class="inline-form">' . $csrf->field() . '<button type="submit" class="react">' . ($c['is_approved_answer'] ? 'Remove approval' : 'Approve answer') . '</button></form>';
        }
    } else {
        $h .= '<details class="comment__report"><summary>Report</summary><form method="post" action="/comments/' . e($id) . '/report" class="form">' . $csrf->field()
            . '<input type="hidden" name="back" value="' . e($back) . '"><label class="field"><span class="field__label">What is wrong?</span><input type="text" name="reason" maxlength="255" required></label>'
            . '<button type="submit" class="btn btn--small btn--ghost">Send to the tutor</button></form></details>';
    }
    $h .= '</div>';
    if (!$reply) {
        $h .= '<ul class="comment__replies">';
        foreach ($c['replies'] as $r) {
            $h .= $item($r, true);
        }
        $h .= '</ul><details class="comment__reply-form"><summary>Reply</summary><form method="post" action="/lessons/' . e($lessonId) . '/comments" class="form">' . $csrf->field()
            . '<input type="hidden" name="parent_id" value="' . e($id) . '"><label class="field"><span class="field__label">Your reply</span><textarea name="body" rows="3" maxlength="2000" required></textarea></label>'
            . '<button type="submit" class="btn btn--small">Reply</button></form></details>';
    }

    return $h . '</li>';
};
?>
<section class="discussion card" id="discussion">
    <div class="discussion__head">
        <h2>Questions and discussion</h2>
        <form method="post" action="/lessons/<?= e($lessonId) ?>/follow" class="inline-form"><?= $csrf->field() ?><input type="hidden" name="on" value="<?= $thread['following'] ? '0' : '1' ?>">
            <button type="submit" class="btn btn--small btn--ghost"><?= $thread['following'] ? 'Stop following' : 'Follow' ?></button></form>
    </div>
    <p class="muted">Ask about anything in this lesson; classmates and the tutor answer. Be kind. Write <strong>@Name</strong> to mention someone here (or @tutor) and <strong>#word</strong> to tag a topic.</p>
    <form method="post" action="/lessons/<?= e($lessonId) ?>/comments" class="form discussion__new"><?= $csrf->field() ?>
        <label class="field"><span class="field__label">Your question or comment</span><textarea name="body" rows="3" maxlength="2000" required></textarea></label>
        <div class="field-row">
            <label class="field"><span class="field__label">At minute <span class="muted">(optional, e.g. 4:30)</span></span><input type="text" name="time" maxlength="6" inputmode="numeric" pattern="\d{1,3}:[0-5]\d" placeholder="4:30"></label>
        </div>
        <div class="form-actions"><button type="submit" class="btn">Post</button></div>
    </form>
    <?php if ($thread['comments'] === []): ?><p class="muted">No questions yet. Be the first!</p><?php else: ?>
        <ul class="comments"><?php foreach ($thread['comments'] as $c) { echo $item($c, false); } ?></ul>
    <?php endif; ?>
</section>
