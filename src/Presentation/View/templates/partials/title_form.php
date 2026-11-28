<?php
/**
 * The tutor sets how a teacher or tutor is addressed (used by "Title + surname" name tags).
 * @var int $id @var string|null $current @var string $back
 */
?>
<form method="post" action="/studio/people/<?= e($id) ?>/title" class="inline-form title-form"><?= $csrf->field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <label>Address as <select name="title" aria-label="Title">
        <option value="">No title</option>
        <?php foreach (\SmartCircle\Support\NameTags::TITLES as $t): ?><option value="<?= e($t) ?>" <?= ($current ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
    </select></label>
    <button type="submit" class="btn btn--small btn--ghost">Save title</button>
</form>
