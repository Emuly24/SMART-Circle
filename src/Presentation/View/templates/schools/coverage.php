<?php
/**
 * @var array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool} $scope
 * @var int $form
 * @var string $subject
 * @var list<array<string, mixed>> $tree
 * @var array<string, string> $statuses
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$url = static fn (int $f, string $s): string => $scope['base'] . '/coverage?form=' . $f . '&subject=' . $s;
$row = static function (array $t, bool $sub) use ($statuses): string {
    $id = (int) $t['id'];
    $html = '<li class="cov-row' . ($sub ? ' cov-row--sub' : '') . '"><span class="cov-row__title">'
        . ($t['unit'] && !$sub ? '<small>' . e($t['unit']) . '</small> ' : '') . e($t['title']) . '</span><span class="cov-row__choices" role="radiogroup" aria-label="' . e($t['title']) . '">';
    foreach (['' => 'Not yet'] + $statuses as $value => $label) {
        $html .= '<label class="cov-choice cov-choice--' . e($value === '' ? 'none' : $value) . '"><input type="radio" name="status[' . $id . ']" value="' . e($value) . '"'
            . ($t['coverage'] === $value ? ' checked' : '') . '> ' . e($label) . '</label>';
    }

    return $html . '</span></li>';
};
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><?= $scope['isTutor'] ? '<a href="' . e($scope['base']) . '">' . e($scope['school']['name']) . '</a>' : '<a href="/dashboard">Dashboard</a>' ?></p>
        <h1>Topics taught<?= $scope['isTutor'] ? ': ' . e($scope['school']['name']) : '' ?></h1>
        <p class="lead">Mark each topic your school has taught (Teaching = the class is on it now). Students may ask SMART Circle for help on taught topics; anything else needs the tutor’s approval.</p>

        <nav class="tabs tabs--small" aria-label="Form">
            <?php for ($f = 1; $f <= 4; $f++): ?><a href="<?= e($url($f, $subject)) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= $f ?></a><?php endfor; ?>
        </nav>
        <nav class="tabs" aria-label="Subject">
            <?php foreach ($scope['subjects'] as $s): ?>
                <a class="tabs__subject tabs__subject--<?= e($s) ?>" href="<?= e($url($form, $s)) ?>" <?= $s === $subject ? 'aria-current="page"' : '' ?>><?= e($names[$s]) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tree === []): ?>
            <div class="card"><p>No topics in the catalogue for this form and subject yet.</p></div>
        <?php else: ?>
            <form method="post" action="<?= e($scope['base']) ?>/coverage" class="coverage-form">
                <?= $csrf->field() ?>
                <input type="hidden" name="form" value="<?= e($form) ?>">
                <input type="hidden" name="subject" value="<?= e($subject) ?>">
                <p class="note">Marking a main topic counts for all its subtopics, so you only need subtopics when the class is part-way through.</p>
                <?php foreach ($tree as $t): ?>
                    <ul class="cov-group topic-item--<?= e($subject) ?>">
                        <?= $row($t, false) ?>
                        <?php foreach ($t['children'] as $c): ?><?= $row($c, true) ?><?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
                <div class="form-actions form-actions--sticky"><button type="submit" class="btn">Save Form <?= e($form) ?> <?= e($names[$subject]) ?></button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
