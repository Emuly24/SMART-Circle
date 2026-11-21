<?php
/**
 * @var array<string, mixed> $topic
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/topics?form=<?= e($topic['form']) ?>&amp;subject=<?= e($topic['subject']) ?>">Form <?= e($topic['form']) ?> <?= e($names[$topic['subject']] ?? $topic['subject']) ?></a></p>
        <h1>Edit topic</h1>
        <form method="post" action="/studio/topics/<?= e($topic['id']) ?>" class="form card">
            <?= $csrf->field() ?>
            <label class="field"><span class="field__label">Title</span><input type="text" name="title" value="<?= e($topic['title']) ?>" maxlength="200" required><?= $err('title') ?></label>
            <div class="field-row">
                <label class="field"><span class="field__label">Unit <span class="muted">(optional)</span></span><input type="text" name="unit" value="<?= e($topic['unit'] ?? '') ?>" maxlength="150"></label>
                <label class="field"><span class="field__label">Order <span class="muted">(smaller comes first)</span></span><input type="number" name="sort_order" value="<?= e($topic['sort_order']) ?>" step="1"></label>
            </div>
            <label class="field">
                <span class="field__label">Learning objectives <span class="muted">(optional, shown to students)</span></span>
                <textarea name="objectives" rows="4"><?= e($topic['objectives'] ?? '') ?></textarea>
            </label>
            <label class="choice"><input type="checkbox" name="is_active" value="1" <?= (bool) $topic['is_active'] ? 'checked' : '' ?>> Students can see this topic</label>
            <div class="form-actions"><button type="submit" class="btn">Save topic</button></div>
        </form>
    </div>
</section>
