<?php
/**
 * @var array<string, mixed> $note
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var list<array{id: int, form: int, subject: string, label: string}> $allTopics
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/notes">Notes &amp; slides</a></p>
        <h1>New notes or slides</h1>
        <form method="post" action="/studio/notes" class="form card question-form">
            <?= $csrf->field() ?>
            <?= $view->partial('note_details', ['note' => $note, 'errors' => $errors, 'names' => $names, 'kinds' => $kinds, 'topicOptions' => $allTopics]) ?>
            <div class="form-actions"><button type="submit" class="btn">Create</button></div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/lesson-topic.js')) ?>" defer></script>
