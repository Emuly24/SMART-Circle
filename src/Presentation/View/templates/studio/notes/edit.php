<?php
/**
 * @var array<string, mixed> $note
 * @var list<array<string, mixed>> $blocks
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var list<array{id: int, form: int, subject: string, label: string}> $topicOptions
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var array{state: string, review: ?array<string, mixed>}|null $review
 * @var list<array<string, mixed>> $reviewHistory
 */
use SmartCircle\Presentation\View\NoteMarkup;

$id = (int) $note['id'];
$base = '/studio/notes/' . $id;
$tool = static fn (string $action, string $label, string $class = 'btn btn--small btn--ghost', array $fields = [], string $confirm = '') =>
    '<form method="post" action="' . e($action) . '"' . ($confirm !== '' ? ' class="js-confirm" data-confirm="' . e($confirm) . '"' : '') . '>' . $csrf->field()
    . implode('', array_map(static fn (string $k, string $v): string => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($fields), $fields))
    . '<button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/notes">Notes &amp; slides</a></p>
        <h1><?= e($note['title']) ?></h1>
        <p><span class="badge badge--<?= e($note['subject']) ?>"><?= e($names[$note['subject']] ?? '') ?></span>
            <span class="muted">Form <?= e($note['form']) ?> · <?= e($note['topic_title']) ?></span>
            <span class="chip <?= $note['status'] === 'published' ? 'chip--lesson' : '' ?>"><?= $note['status'] === 'published' ? 'Published' : 'Draft' ?></span></p>

        <div class="card assess-actions">
            <div class="decision__buttons">
                <?= $note['status'] === 'published'
                    ? $tool($base . '/unpublish', 'Unpublish', 'btn btn--small btn--ghost')
                    : $tool($base . '/publish', 'Publish to students', 'btn btn--small btn--green') ?>
                <?= $tool($base . '/delete', 'Delete', 'btn btn--small btn--danger', [], 'Delete these notes for good?') ?>
            </div>
            <p class="note">Students read up to each checkpoint. The next part is not even sent to their phone until they submit the exercise.</p>
            <div class="library-toggle">
                <?php if (!empty($note['in_library'])): ?>
                    <p>📚 <strong>In the free library</strong><?= $note['status'] === 'published' ? ': <a href="/library/notes/' . e($id) . '">see the public handout</a>' : ' once published' ?>. Anyone can read the text and pictures; the exercises stay for admitted students.</p>
                    <?= $tool($base . '/library/off', 'Take out of the free library') ?>
                <?php else: ?>
                    <p>📚 Share the text and pictures with everyone as a free, printable handout in our brand style. The exercises stay for admitted students.</p>
                    <?= $tool($base . '/library/on', 'Show in the free library', 'btn btn--small btn--green') ?>
                <?php endif; ?>
            </div>
            <?php if ($review !== null): $rs = $review['state']; ?>
                <div class="review-status" id="reviews">
                    <?php if ($rs === 'approved'): ?>
                        <p>✓ <strong>Checked by a qualified teacher:</strong> <?= e($review['review']['reviewer_name']) ?><?= $review['review']['school_name'] ? ', ' . e($review['review']['school_name']) : '' ?>. Their name and signature show on these notes. Any change you make now will need a new check.</p>
                    <?php elseif ($rs === 'stale'): ?>
                        <p>✎ <strong>Changed since <?= e($review['review']['reviewer_name']) ?> approved it.</strong> The signature is hidden until a teacher checks it again.</p>
                        <?= $tool($base . '/review', 'Ask a teacher to check again', 'btn btn--small btn--green') ?>
                    <?php elseif ($rs === 'changes'): ?>
                        <p>✎ <strong><?= e($review['review']['reviewer_name']) ?> suggested changes</strong> (below). Make them, then ask again.</p>
                        <?= $tool($base . '/review', 'Ask a teacher to check again', 'btn btn--small btn--green') ?>
                    <?php elseif ($rs === 'requested'): ?>
                        <p>⏳ <strong>Waiting for a partner teacher to check it.</strong> When one approves it, their name and signature appear on these notes.</p>
                        <?= $tool($base . '/review', 'Remind the teachers') ?>
                    <?php else: ?>
                        <p>👩🏽‍🏫 Ask a partner teacher who teaches this subject to check these notes and exercises. If they approve, their name, school and signature appear on the notes and the handout.</p>
                        <?= $tool($base . '/review', 'Ask a teacher to check', 'btn btn--small btn--green') ?>
                    <?php endif; ?>
                    <?php if ($reviewHistory !== []): ?><?= $view->partial('review_history', ['history' => $reviewHistory]) ?><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <ol class="note-blocks">
            <?php foreach ($blocks as $i => $b): $bid = (int) $b['id']; ?>
                <li class="card note-block note-block--<?= e($b['type']) ?>" id="block-<?= e($bid) ?>">
                    <div class="note-block__bar">
                        <span class="chip"><?= e(['text' => 'Text', 'image' => 'Picture', 'checkpoint' => $b['is_final'] ? 'End of unit exercise' : 'Checkpoint ' . ($b['number'] ?? '')][$b['type']]) ?></span>
                        <span class="note-block__tools">
                            <?= $tool($base . '/blocks/' . $bid . '/move', '↑', 'btn btn--small btn--ghost', ['direction' => 'up']) ?>
                            <?= $tool($base . '/blocks/' . $bid . '/move', '↓', 'btn btn--small btn--ghost', ['direction' => 'down']) ?>
                            <?= $tool($base . '/blocks/' . $bid . '/delete', 'Remove', 'btn btn--small btn--ghost', [], 'Remove this part?') ?>
                        </span>
                    </div>
                    <?php if ($b['type'] === 'text'): ?>
                        <div class="note-prose"><?= NoteMarkup::render((string) $b['body']) ?></div>
                        <details class="note-block__edit"><summary>Edit text</summary>
                            <form method="post" action="<?= e($base) ?>/blocks/<?= e($bid) ?>" class="form"><?= $csrf->field() ?>
                                <textarea name="body" rows="10" class="note-editor"><?= e($b['body']) ?></textarea>
                                <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
                            </form>
                        </details>
                    <?php elseif ($b['type'] === 'image'): ?>
                        <figure class="note-figure note-figure--<?= e($b['align'] ?? 'center') ?>"><img src="<?= e($base) ?>/images/<?= e($bid) ?>" alt="<?= e($b['body'] ?? 'Slide') ?>" loading="lazy">
                            <?php if ($b['body']): ?><figcaption><?= e($b['body']) ?></figcaption><?php endif; ?></figure>
                        <form method="post" action="<?= e($base) ?>/blocks/<?= e($bid) ?>/align" class="inline-form align-form"><?= $csrf->field() ?>
                            <span class="muted">Position:</span>
                            <?php foreach (\SmartCircle\Application\Notes\NoteService::ALIGN as $ak => $al): ?><button type="submit" name="align" value="<?= e($ak) ?>" class="btn btn--small <?= ($b['align'] ?? 'center') === $ak ? '' : 'btn--ghost' ?>" aria-pressed="<?= ($b['align'] ?? 'center') === $ak ? 'true' : 'false' ?>"><?= e(explode(' ', $al)[0]) ?></button><?php endforeach; ?>
                        </form>
                        <details class="note-block__edit"><summary>Edit caption</summary>
                            <form method="post" action="<?= e($base) ?>/blocks/<?= e($bid) ?>" class="form"><?= $csrf->field() ?>
                                <input type="text" name="body" value="<?= e($b['body'] ?? '') ?>" maxlength="500" class="other-input">
                                <div class="form-actions"><button type="submit" class="btn btn--small">Save</button></div>
                            </form>
                        </details>
                    <?php else: ?>
                        <p class="checkpoint-summary"><strong><?= e($b['questions']) ?> question<?= (int) $b['questions'] === 1 ? '' : 's' ?></strong>
                            <?= (int) $b['questions'] === 0 ? '<span class="tag">Add questions before publishing</span>' : '' ?>
                            <?= (int) $b['answered'] > 0 ? '<span class="muted">· ' . e($b['answered']) . ((int) $b['answered'] === 1 ? ' student' : ' students') . ' started</span>' : '' ?></p>
                        <p><a class="btn btn--small" href="/studio/assessments/<?= e($b['assessment_id']) ?>">Choose questions / see answers</a></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($blocks === []): ?><div class="card"><p>Empty so far. Add your first section below.</p></div><?php endif; ?>

        <div class="card section-card" id="add">
            <h2 class="h3">Add a text section</h2>
            <form method="post" action="<?= e($base) ?>/text" class="form"><?= $csrf->field() ?>
                <textarea name="body" rows="8" class="note-editor" placeholder="# Density&#10;Density is mass per unit volume: ρ = m / V.&#10;&#10;- Water: 1000 kg/m^3&#10;&#10;> Real life: a dugout canoe floats on Lake Malawi because it is less dense than water."></textarea>
                <details class="markup-help"><summary>How to format</summary>
                    <ul>
                        <li><code># Heading</code>, <code>## Smaller heading</code></li>
                        <li><code>- item</code> for a list, <code>1. step</code> for numbered steps</li>
                        <li><code>**bold**</code>, <code>*italic*</code></li>
                        <li><code>= v = u + at</code> an equation or calculation step, centred (one per line; steps line up)</li>
                        <li><code>&lt;=</code> ≤, <code>&gt;=</code> ≥, <code>!=</code> ≠, <code>+-</code> ±, <code>sqrt</code> √; in an equation line <code>*</code> is ×</li>
                        <li><code>H_2O</code>, <code>CO_{2}</code> for subscripts; <code>x^2</code>, <code>10^{-3}</code> for powers</li>
                        <li><code>-&gt;</code> becomes →, <code>&lt;=&gt;</code> becomes ⇌</li>
                        <li><code>&gt; Real life: …</code> makes an amber real-life box (also <code>Where it is used:</code>, <code>Why it matters:</code>, <code>Did you know?</code>). <code>&gt; Remember: …</code> or <code>Key idea:</code> makes a blue box, <code>Tip:</code> or <code>Example:</code> a green one, <code>Careful:</code> a warning. Lines that all start with <code>&gt;</code> stay in one box.</li>
                        <li>A blank line starts a new paragraph.</li>
                    </ul>
                </details>
                <div class="form-actions"><button type="submit" class="btn btn--small">Add section</button></div>
            </form>
        </div>
        <div class="detail-grid">
            <div class="card">
                <h2 class="h3">Add a picture or slide</h2>
                <form method="post" action="<?= e($base) ?>/image" enctype="multipart/form-data" class="form"><?= $csrf->field() ?>
                    <label class="field"><span class="field__label">Picture (JPG, PNG or WebP, up to 5 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
                    <label class="field"><span class="field__label">Caption <span class="muted">(optional)</span></span><input type="text" name="caption" maxlength="500"></label>
                    <label class="field"><span class="field__label">Position</span><select name="align"><?php foreach (\SmartCircle\Application\Notes\NoteService::ALIGN as $ak => $al): ?><option value="<?= e($ak) ?>"><?= e($al) ?></option><?php endforeach; ?></select></label>
                    <p class="note">For slides, export each slide as a picture and add them in order. A board page saved with “Picture” can be added here too.</p>
                    <div class="form-actions"><button type="submit" class="btn btn--small">Upload</button></div>
                </form>
            </div>
            <div class="card drawpad-card">
                <h2 class="h3">Draw a picture</h2>
                <p class="muted">Sketch a diagram, graph or circuit with your finger or Apple Pencil; it is added like a picture.</p>
                <button type="button" class="btn btn--small js-drawpad-open" data-action="<?= e($base) ?>/image" hidden>Open the drawing pad</button>
                <noscript><p class="note">The drawing pad needs JavaScript.</p></noscript>
            </div>
            <div class="card">
                <h2 class="h3">Add an exercise</h2>
                <p class="muted">Students must submit it before the next part opens.</p>
                <div class="decision__buttons">
                    <?= $tool($base . '/checkpoint', 'Add a checkpoint', 'btn btn--small') ?>
                    <?= $tool($base . '/checkpoint', 'Add the end-of-unit exercise', 'btn btn--small btn--ghost', ['final' => '1']) ?>
                </div>
            </div>
        </div>

        <details class="card section-card"<?= $errors !== [] ? ' open' : '' ?>>
            <summary><strong>Edit details</strong></summary>
            <form method="post" action="<?= e($base) ?>" class="form question-form"><?= $csrf->field() ?>
                <?= $view->partial('note_details', ['note' => $note, 'errors' => $errors, 'names' => $names, 'kinds' => $kinds, 'topicOptions' => $topicOptions]) ?>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save details</button></div>
            </form>
        </details>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
<script src="<?= e($asset('js/drawpad.js')) ?>" defer></script>
