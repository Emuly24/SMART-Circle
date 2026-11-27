<?php
/**
 * @var array<string, mixed> $lesson
 * @var array<string, string> $errors
 * @var list<string> $missing
 * @var array<string, string> $subjects
 * @var array<string, string> $sections
 * @var list<array{id: int, form: int, subject: string, label: string}> $topicOptions
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$id = (int) $lesson['id'];
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$hints = [
    'why_it_matters' => 'e.g. Choosing the right fertiliser can mean a better maize harvest.',
    'where_in_malawi' => 'e.g. Acidic soils and why farmers apply lime; ESCOM power lines; boreholes.',
    'careers' => 'e.g. Agronomist, pharmacist, lab technician, electrical engineer.',
    'try_at_home' => 'A safe activity with things found at home, e.g. a red-cabbage indicator.',
];
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/lessons">My lessons</a><?php if (empty($isGuest)): ?> · <a href="/lessons/<?= e($id) ?>">Watch</a><?php endif; ?></p>
        <h1>Edit lesson</h1>
        <p>
            <?= $lesson['status'] === 'published' ? '<span class="tag tag--ready">Published</span>' : '<span class="tag">Draft</span>' ?>
            <span class="muted">· <?= e(gmdate('i:s', intdiv((int) ($lesson['duration_ms'] ?? 0), 1000))) ?> · <?= e(number_format((int) ($lesson['size_bytes'] ?? 0) / 1048576, 1)) ?> MB · <?= !empty($lesson['has_voice']) ? 'with voice' : 'no voice' ?></span>
        </p>

        <?php if (!empty($guestCredit)): ?><div class="alert alert--info">Recorded by guest teacher <strong><?= e($guestCredit['name']) ?></strong><?= $guestCredit['school'] ? ', ' . e($guestCredit['school']) : '' ?>. Watch it before you publish; students will see their name on it.</div><?php endif; ?>
        <?php if (!empty($isGuest) && !empty($lesson['guest_submitted_at']) && $lesson['status'] !== 'published'): ?><div class="alert alert--info">Sent to the tutor <?= e(date('j M', (int) strtotime((string) $lesson['guest_submitted_at']))) ?>. You will be told when it is published.</div><?php endif; ?>
        <?php if ($missing !== []): ?>
            <div class="alert alert--error" role="alert">
                <strong>Not published yet.</strong> Students only see a lesson once every section is filled in. Still missing:
                <?= e(implode(', ', $missing)) ?>.
            </div>
        <?php endif; ?>

        <form method="post" action="/studio/lessons/<?= e($id) ?>" class="form card">
            <?= $csrf->field() ?>
            <label class="field">
                <span class="field__label">Title</span>
                <input type="text" name="title" value="<?= e($lesson['title'] ?? '') ?>" maxlength="200" required>
                <?= $err('title') ?>
            </label>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Form</span>
                    <select name="form">
                        <option value="0">Choose…</option>
                        <?php for ($f = 1; $f <= 4; $f++): ?>
                            <option value="<?= $f ?>" <?= (int) ($lesson['form'] ?? 0) === $f ? 'selected' : '' ?>>Form <?= $f ?></option>
                        <?php endfor; ?>
                    </select>
                    <?= $err('form') ?>
                </label>
                <label class="field">
                    <span class="field__label">Subject</span>
                    <select name="subject">
                        <option value="">Choose…</option>
                        <?php foreach ($subjects as $key => $name): ?>
                            <option value="<?= e($key) ?>" <?= ($lesson['subject'] ?? '') === $key ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('subject') ?>
                </label>
            </div>
            <label class="field">
                <span class="field__label">Topic <span class="muted">(links the lesson to students’ requests)</span></span>
                <select name="topic_id" class="js-topic-select">
                    <option value="0">No topic</option>
                    <?php foreach ($topicOptions as $opt): ?>
                        <option value="<?= e($opt['id']) ?>" data-form="<?= e($opt['form']) ?>" data-subject="<?= e($opt['subject']) ?>" <?= (int) ($lesson['topic_id'] ?? 0) === $opt['id'] ? 'selected' : '' ?>>Form <?= e($opt['form']) ?> · <?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('topic_id') ?>
            </label>
            <label class="field">
                <span class="field__label">Short summary <span class="muted">(optional)</span></span>
                <textarea name="summary" rows="2"><?= e($lesson['summary'] ?? '') ?></textarea>
            </label>

            <fieldset class="real-life-fields">
                <legend>Real life: required before publishing</legend>
                <?php foreach ($sections as $field => $label): ?>
                    <label class="field">
                        <span class="field__label"><?= e($label) ?></span>
                        <textarea name="<?= e($field) ?>" rows="3" placeholder="<?= e($hints[$field]) ?>"><?= e($lesson[$field] ?? '') ?></textarea>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn--ghost">Save</button>
                <?php if ($lesson['status'] !== 'published'): ?>
                    <button type="submit" name="action" value="publish" class="btn btn--green"><?= !empty($isGuest) ? 'Save and send to the tutor' : 'Save and publish' ?></button>
                <?php else: ?>
                    <button type="submit" name="action" value="save" class="btn">Save changes</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($review)): $rs = $review['state']; ?>
            <div class="card section-card" id="reviews">
                <h2 class="h3">Checked by a qualified teacher</h2>
                <?php if ($rs === 'approved'): ?>
                    <p>✓ <strong><?= e($review['review']['reviewer_name']) ?></strong><?= $review['review']['school_name'] ? ', ' . e($review['review']['school_name']) : '' ?> approved this lesson. Students see their name and signature. Any change needs a new check.</p>
                <?php elseif ($rs === 'stale'): ?>
                    <p>✎ <strong>Changed since <?= e($review['review']['reviewer_name']) ?> approved it.</strong> The signature is hidden until a teacher checks it again.</p>
                <?php elseif ($rs === 'changes'): ?>
                    <p>✎ <strong><?= e($review['review']['reviewer_name']) ?> suggested changes</strong> (below).</p>
                <?php elseif ($rs === 'requested'): ?>
                    <p>⏳ Waiting for a partner teacher to watch and check it.</p>
                <?php else: ?>
                    <p>Ask a partner teacher of this subject to watch the lesson (and its PDF handout) and check it. If they approve, their name and signature appear on it.</p>
                <?php endif; ?>
                <?php if ($rs !== 'approved'): ?><form method="post" action="/studio/lessons/<?= e($id) ?>/review" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--green"><?= $rs === 'none' ? 'Ask a teacher to check' : ($rs === 'requested' ? 'Remind the teachers' : 'Ask a teacher to check again') ?></button></form><?php endif; ?>
                <?php if (!empty($reviewHistory)): ?><?= $view->partial('review_history', ['history' => $reviewHistory]) ?><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="card library-box" id="handout">
            <h2>PDF handout <span class="muted">(optional)</span></h2>
            <p>Attach a handout you made elsewhere (for example in Word, saved as PDF). Up to 9 MB.</p>
            <p class="note">An uploaded PDF keeps its own look: SMART Circle cannot restyle it. For handouts in our brand colours, write the notes in <a href="/studio/notes">Notes &amp; slides</a> and tick "Show in the free library" there.</p>
            <?php if (!empty($lesson['handout_path'])): ?>
                <p>✓ Handout attached (<?= e(number_format((int) $lesson['handout_bytes'] / 1048576, 1)) ?> MB) · <a href="/studio/lessons/<?= e($id) ?>/handout">Open it</a>
                    · <?= !empty($lesson['handout_public']) ? '<strong>In the free library</strong>' . ($lesson['status'] === 'published' ? '' : ' once this lesson is published') : 'Not in the library' ?></p>
                <div class="decision__buttons">
                    <form method="post" action="/studio/lessons/<?= e($id) ?>/handout/<?= !empty($lesson['handout_public']) ? 'private' : 'public' ?>"><?= $csrf->field() ?>
                        <button type="submit" class="btn btn--small <?= !empty($lesson['handout_public']) ? 'btn--ghost' : 'btn--green' ?>"><?= !empty($lesson['handout_public']) ? 'Take out of the free library' : 'Show in the free library' ?></button></form>
                    <form method="post" action="/studio/lessons/<?= e($id) ?>/handout/remove" class="js-confirm" data-confirm="Remove this handout?"><?= $csrf->field() ?>
                        <button type="submit" class="btn btn--small btn--ghost">Remove</button></form>
                </div>
            <?php endif; ?>
            <form method="post" action="/studio/lessons/<?= e($id) ?>/handout" enctype="multipart/form-data" class="form"><?= $csrf->field() ?>
                <label class="field"><span class="field__label"><?= empty($lesson['handout_path']) ? 'PDF handout' : 'Replace with another PDF' ?></span>
                    <input type="file" name="handout" accept="application/pdf,.pdf" required></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Upload handout</button></div>
            </form>
        </div>

        <?php if (empty($isGuest) || $lesson['status'] !== 'published'): ?>
        <div class="danger-zone">
            <?php if ($lesson['status'] === 'published'): ?>
                <form method="post" action="/studio/lessons/<?= e($id) ?>/unpublish">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small btn--ghost">Unpublish</button>
                </form>
            <?php endif; ?>
            <form method="post" action="/studio/lessons/<?= e($id) ?>/delete" class="js-confirm" data-confirm="Delete this lesson for good? Students will lose access.">
                <?= $csrf->field() ?>
                <button type="submit" class="btn btn--small btn--danger">Delete lesson</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
<script src="<?= e($asset('js/lesson-topic.js')) ?>" defer></script>
