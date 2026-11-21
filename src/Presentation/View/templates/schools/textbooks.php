<?php
/**
 * @var array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool} $scope
 * @var list<array<string, mixed>> $books
 * @var list<array<string, mixed>> $known
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$mine = array_column($books, 'id');
$others = array_values(array_filter($known, static fn (array $b): bool => !in_array($b['id'], $mine, true)));
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><?= $scope['isTutor'] ? '<a href="' . e($scope['base']) . '">' . e($scope['school']['name']) . '</a>' : '<a href="/dashboard">Dashboard</a>' ?></p>
        <h1>Textbooks<?= $scope['isTutor'] ? ': ' . e($scope['school']['name']) : '' ?></h1>
        <p class="lead">The books students use at <?= $scope['isTutor'] ? 'this' : 'your' ?> school. For each topic, add the chapter and pages, and every lesson page will point students to the right part of their own book.</p>

        <?php if ($books === []): ?>
            <div class="card"><p>No textbooks listed yet.</p></div>
        <?php else: ?>
            <ul class="book-list">
                <?php foreach ($books as $b): ?>
                    <li class="book-row">
                        <a href="<?= e($scope['base']) ?>/textbooks/<?= e($b['id']) ?>">
                            <span class="badge badge--<?= e($b['subject']) ?>"><?= e($names[$b['subject']] ?? $b['subject']) ?></span>
                            <span class="muted">Form <?= e($b['form']) ?></span>
                            <strong><?= e($b['title']) ?></strong><?= $b['edition'] !== '' ? ' <span class="muted">(' . e($b['edition']) . ')</span>' : '' ?>
                            <span class="chip<?= (int) $b['refs'] > 0 ? ' chip--lesson' : '' ?>"><?= e($b['refs']) ?> topics with pages</span>
                        </a>
                        <form method="post" action="<?= e($scope['base']) ?>/textbooks/<?= e($b['id']) ?>/remove" class="js-confirm" data-confirm="Remove this book from the school’s list? The page numbers stay saved.">
                            <?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="<?= e($scope['base']) ?>/textbooks" class="form card">
            <?= $csrf->field() ?>
            <h2 class="h3">Add a textbook</h2>
            <?php if ($others !== []): ?>
                <label class="field">
                    <span class="field__label">Already used by another school</span>
                    <select name="known_id">
                        <option value="0">— choose, or type a new book below —</option>
                        <?php foreach ($others as $b): ?><option value="<?= e($b['id']) ?>">Form <?= e($b['form']) ?> <?= e($names[$b['subject']] ?? '') ?>: <?= e($b['title']) ?><?= $b['edition'] !== '' ? ' (' . e($b['edition']) . ')' : '' ?></option><?php endforeach; ?>
                    </select>
                    <?= $err('known_id') ?>
                </label>
                <p class="muted">Or a new book:</p>
            <?php else: ?>
                <input type="hidden" name="known_id" value="0"><?= $err('known_id') ?>
            <?php endif; ?>
            <div class="field-row">
                <label class="field"><span class="field__label">Subject</span>
                    <select name="subject"><option value="">Choose…</option>
                        <?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= ($old['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
                    </select><?= $err('subject') ?></label>
                <label class="field"><span class="field__label">Form</span>
                    <select name="form"><option value="0">Choose…</option>
                        <?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (int) ($old['form'] ?? 0) === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?>
                    </select><?= $err('form') ?></label>
            </div>
            <label class="field"><span class="field__label">Title</span><input type="text" name="title" value="<?= e($old['title'] ?? '') ?>" maxlength="200" placeholder="e.g. Strides in Physics Book 3"><?= $err('title') ?></label>
            <div class="field-row">
                <label class="field"><span class="field__label">Edition <span class="muted">(optional)</span></span><input type="text" name="edition" value="<?= e($old['edition'] ?? '') ?>" maxlength="60"></label>
                <label class="field"><span class="field__label">Publisher <span class="muted">(optional)</span></span><input type="text" name="publisher" value="<?= e($old['publisher'] ?? '') ?>" maxlength="120"></label>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--small">Add textbook</button></div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
