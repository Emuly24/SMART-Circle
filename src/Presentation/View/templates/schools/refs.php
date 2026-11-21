<?php
/**
 * @var array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool} $scope
 * @var array<string, mixed> $book
 * @var list<array<string, mixed>> $tree
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$row = static function (array $t, bool $sub): string {
    $id = (int) $t['id'];

    return '<li class="ref-row' . ($sub ? ' ref-row--sub' : '') . '"><span class="ref-row__title">' . e($t['title']) . '</span>'
        . '<label><span class="sr-only">Chapter for ' . e($t['title']) . '</span><input type="text" name="chapter[' . $id . ']" value="' . e($t['ref']['chapter']) . '" maxlength="60" placeholder="Chapter"></label>'
        . '<label><span class="sr-only">Pages for ' . e($t['title']) . '</span><input type="text" name="pages[' . $id . ']" value="' . e($t['ref']['pages']) . '" maxlength="60" placeholder="Pages, e.g. 45–52"></label></li>';
};
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="<?= e($scope['base']) ?>/textbooks">Textbooks</a></p>
        <p><span class="badge badge--<?= e($book['subject']) ?>"><?= e($names[$book['subject']] ?? $book['subject']) ?></span> <span class="muted">Form <?= e($book['form']) ?></span></p>
        <h1><?= e($book['title']) ?></h1>
        <p class="lead">Where each topic is in this book. Leave a row empty if the book does not cover it. Any school using this book benefits.</p>
        <?php if ($tree === []): ?>
            <div class="card"><p>No topics in the catalogue for this form and subject yet.</p></div>
        <?php else: ?>
            <form method="post" action="<?= e($scope['base']) ?>/textbooks/<?= e($book['id']) ?>" class="coverage-form">
                <?= $csrf->field() ?>
                <?php foreach ($tree as $t): ?>
                    <ul class="cov-group topic-item--<?= e($book['subject']) ?>">
                        <?= $row($t, false) ?>
                        <?php foreach ($t['children'] as $c): ?><?= $row($c, true) ?><?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
                <div class="form-actions form-actions--sticky"><button type="submit" class="btn">Save page numbers</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
