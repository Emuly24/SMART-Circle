<?php
/**
 * Tutor: one school at a glance.
 * @var array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool} $scope
 * @var array<string, array{covered: int, total: int}> $progress
 * @var array<int, int> $students
 * @var list<array<string, mixed>> $top
 * @var list<array<string, mixed>> $books
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/partners">School partners</a></p>
        <h1><?= e($scope['school']['name']) ?></h1>
        <p class="lead"><?= e($scope['school']['district']) ?> · <?= e(array_sum($students)) ?> SMART Circle student<?= array_sum($students) === 1 ? '' : 's' ?><?= $students !== [] ? ' (' . e(implode(', ', array_map(static fn (int $f, int $n): string => "Form $f: $n", array_keys($students), $students))) . ')' : '' ?></p>
        <?= $view->partial('school_overview', ['scope' => $scope, 'progress' => $progress, 'top' => $top, 'bookCount' => count($books), 'names' => $names]) ?>
    </div>
</section>
