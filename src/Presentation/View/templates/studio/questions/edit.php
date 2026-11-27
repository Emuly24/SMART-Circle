<?php
/**
 * @var array<string, mixed> $q
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $types
 * @var list<array{id: int, form: int, subject: string, label: string}> $topicOptions
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$action = $q['id'] === null ? '/studio/questions' : '/studio/questions/' . $q['id'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/questions?form=<?= e($q['form']) ?>&amp;subject=<?= e($q['subject']) ?>">Question bank</a></p>
        <h1><?= $q['id'] === null ? 'New question' : 'Edit question' ?></h1>
        <form method="post" action="<?= e($action) ?>" class="form card question-form">
            <?= $csrf->field() ?>
            <?= $view->partial('question_editor', ['q' => $q, 'errors' => $errors, 'names' => $names, 'types' => $types, 'topicOptions' => $topicOptions]) ?>
            <div class="form-actions"><button type="submit" class="btn">Save question</button></div>
        </form>
        <?php if ($q['id'] !== null): ?>
            <form method="post" action="/studio/questions/<?= e($q['id']) ?>/delete" class="danger-zone js-confirm" data-confirm="Delete this question?">
                <?= $csrf->field() ?><button type="submit" class="btn btn--small btn--danger">Delete question</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/question-form.js')) ?>" defer></script>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
