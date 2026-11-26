<?php
/**
 * "Term 1 · Week 11 of 14" or "Holiday: Term 2 opens on …", and MANEB countdowns.
 * @var array<string, mixed> $today
 * @var list<array<string, mixed>>|null $maneb
 */
$maneb = $maneb ?? [];
?>
<?php if (($today['term'] ?? null) !== null || ($today['next'] ?? null) !== null): ?>
    <p class="term-strip">
        <?php if ($today['in_term']): ?>
            <span class="term-strip__now"><?= e($today['term']['year_name']) ?> · Term <?= e($today['term']['number']) ?> · Week <?= e($today['week']) ?> of <?= e($today['weeks']) ?></span>
        <?php elseif ($today['next'] !== null): ?>
            <span class="term-strip__now">Holiday · Term <?= e($today['next']['number']) ?> opens <?= e(date('D j M', (int) strtotime((string) $today['next']['opens_on']))) ?></span>
        <?php else: ?>
            <span class="term-strip__now">Holiday after Term <?= e($today['term']['number']) ?></span>
        <?php endif; ?>
        <?php foreach ($maneb as $m): ?>
            <span class="term-strip__maneb"><?= $m['days'] > 0 ? e($m['days']) . ' days to ' : 'Now: ' ?><?= e($m['title']) ?></span>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
