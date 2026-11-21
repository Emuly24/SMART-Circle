<?php
/**
 * @var int $form
 * @var string $subject
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $tree
 * @var array<string, string> $errors
 * @var string $list
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$q = static fn (int $f, string $s): string => '/studio/topics?form=' . $f . '&subject=' . $s;
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a> · <a href="/studio/requests">Topic requests</a></p>
        <h1>Topic catalogue</h1>
        <p class="lead">The preloaded syllabus students choose from. Rename, reorder or hide topics; add missing ones.</p>
        <nav class="tabs tabs--small" aria-label="Form">
            <?php for ($f = 1; $f <= 4; $f++): ?><a href="<?= e($q($f, $subject)) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= $f ?></a><?php endfor; ?>
        </nav>
        <nav class="tabs" aria-label="Subject">
            <?php foreach ($names as $s => $n): ?>
                <a class="tabs__subject tabs__subject--<?= e($s) ?>" href="<?= e($q($form, $s)) ?>" <?= $s === $subject ? 'aria-current="page"' : '' ?>><?= e($n) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tree === []): ?>
            <div class="card"><p>No topics yet for Form <?= e($form) ?> <?= e($names[$subject]) ?>. Add them below or paste a list.</p></div>
        <?php else: ?>
            <ol class="topic-list topic-list--edit">
                <?php foreach ($tree as $t): ?>
                    <li class="topic-item topic-item--<?= e($subject) ?><?= (bool) $t['is_active'] ? '' : ' is-hidden-topic' ?>">
                        <a class="topic-item__main" href="/studio/topics/<?= e($t['id']) ?>">
                            <?php if ($t['unit']): ?><span class="topic-item__unit"><?= e($t['unit']) ?></span><?php endif; ?>
                            <span class="topic-item__title"><?= e($t['title']) ?><?= (bool) $t['is_active'] ? '' : ' <span class="tag">Hidden</span>' ?></span>
                        </a>
                        <?php if ($t['children'] !== []): ?>
                            <ul class="subtopics">
                                <?php foreach ($t['children'] as $c): ?>
                                    <li class="<?= (bool) $c['is_active'] ? '' : 'is-hidden-topic' ?>"><a href="/studio/topics/<?= e($c['id']) ?>"><span><?= e($c['title']) ?></span><?= (bool) $c['is_active'] ? '' : '<span class="tag">Hidden</span>' ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="detail-grid">
            <form method="post" action="/studio/topics" class="form card">
                <?= $csrf->field() ?>
                <h2 class="h3">Add a topic</h2>
                <input type="hidden" name="form" value="<?= e($form) ?>">
                <input type="hidden" name="subject" value="<?= e($subject) ?>">
                <label class="field">
                    <span class="field__label">Inside</span>
                    <select name="parent_id">
                        <option value="0">— a new main topic —</option>
                        <?php foreach ($tree as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['title']) ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('parent_id') ?>
                </label>
                <label class="field"><span class="field__label">Unit <span class="muted">(optional)</span></span><input type="text" name="unit" maxlength="150" placeholder="e.g. Unit 4"></label>
                <label class="field"><span class="field__label">Title</span><input type="text" name="title" maxlength="200" required><?= $err('title') ?></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Add topic</button></div>
            </form>

            <form method="post" action="/studio/topics/import" class="form card">
                <?= $csrf->field() ?>
                <h2 class="h3">Paste a list</h2>
                <input type="hidden" name="form" value="<?= e($form) ?>">
                <input type="hidden" name="subject" value="<?= e($subject) ?>">
                <label class="field">
                    <span class="field__label">One topic per line. Subtopics in brackets, separated by commas.</span>
                    <textarea name="list" rows="6" placeholder="Unit 1: Measurement (Length, Mass, Time)&#10;Unit 2: Density"><?= e($list) ?></textarea>
                    <?= $err('list') ?>
                </label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Add all</button></div>
            </form>
        </div>
    </div>
</section>
