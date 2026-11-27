<?php
/**
 * @var list<array<string, mixed>> $terms
 * @var int|null $termId
 * @var array<string, string> $extra hidden fields to keep (e.g. form)
 */
$extra = $extra ?? [];
?>
<?php if ($terms !== []): ?>
    <form method="get" class="filters">
        <label>Term
            <select name="term">
                <?php foreach ($terms as $t): ?><option value="<?= e($t['id']) ?>" <?= (int) $t['id'] === $termId ? 'selected' : '' ?>><?= e($t['year_name']) ?> Term <?= e($t['number']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php foreach ($extra as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <button type="submit" class="btn btn--small btn--ghost">Show</button>
    </form>
<?php endif; ?>
