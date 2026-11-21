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
        <p class="crumbs"><a href="/studio/lessons">My lessons</a> · <a href="/lessons/<?= e($id) ?>">Watch</a></p>
        <h1>Edit lesson</h1>
        <p>
            <?= $lesson['status'] === 'published' ? '<span class="tag tag--ready">Published</span>' : '<span class="tag">Draft</span>' ?>
            <span class="muted">· <?= e(gmdate('i:s', intdiv((int) ($lesson['duration_ms'] ?? 0), 1000))) ?> · <?= e(number_format((int) ($lesson['size_bytes'] ?? 0) / 1048576, 1)) ?> MB · <?= !empty($lesson['has_voice']) ? 'with voice' : 'no voice' ?></span>
        </p>

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
                    <button type="submit" name="action" value="publish" class="btn btn--green">Save and publish</button>
                <?php else: ?>
                    <button type="submit" name="action" value="save" class="btn">Save changes</button>
                <?php endif; ?>
            </div>
        </form>

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
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
<script src="<?= e($asset('js/lesson-topic.js')) ?>" defer></script>
