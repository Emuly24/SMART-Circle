<?php
/**
 * @var array<string, mixed> $a
 * @var array<string, string> $errors
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/assessments">Exams &amp; assignments</a></p>
        <h1>New exam or assignment</h1>
        <form method="post" action="/studio/assessments" class="form card">
            <?= $csrf->field() ?>
            <?= $view->partial('assessment_details', ['a' => $a, 'errors' => $errors, 'names' => $names, 'kinds' => $kinds]) ?>
            <div class="form-actions"><button type="submit" class="btn">Create, then add questions</button></div>
        </form>
    </div>
</section>
