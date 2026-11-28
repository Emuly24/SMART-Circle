<?php
/**
 * One-click name tags above a message or email box. Each button types a tag such as {first_name}; every
 * reader then sees their own name there, in brand green. Needs public/assets/js/name-tags.js.
 * @var bool|null $guardians also offer the student's names (emails to parents and guardians)
 */
use SmartCircle\Support\NameTags;

$groups = [
    'For students' => ['first_name', 'surname', 'full_name'],
    'For teachers and tutors' => ['title_surname', 'title_full_name'],
];
if (!empty($guardians)) {
    $groups['For parents and guardians'] = ['student_first_name', 'student_full_name'];
}
?>
<div class="name-tags js-name-tags" hidden>
    <span class="name-tags__label">Insert a name:</span>
    <?php foreach ($groups as $label => $keys): ?>
        <span class="name-tags__group" role="group" aria-label="<?= e($label) ?>">
            <span class="name-tags__who"><?= e($label) ?></span>
            <?php foreach ($keys as $k): ?><button type="button" class="name-tag-btn" data-name-tag="<?= e($k) ?>" title="Puts {<?= e($k) ?>} here: each person sees their own"><?= e(NameTags::TAGS[$k]) ?></button><?php endforeach; ?>
        </span>
    <?php endforeach; ?>
    <span class="name-tags__hint">Each person sees their own name, in green. With no title saved, “Title + …” uses their full name.</span>
</div>
