<?php
/**
 * A member's photo, or their initials. @var int $id @var string $name @var string|null $at @var string $size sm|md|lg
 */
$size ??= 'sm';
$words = preg_split('/\s+/', trim((string) $name)) ?: [];
$initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1) . (count($words) > 1 ? mb_substr((string) end($words), 0, 1) : ''));
?>
<?php if (!empty($at)): ?><img class="avatar avatar--<?= e($size) ?>" src="/avatars/<?= e($id) ?>?v=<?= e(strtotime((string) $at)) ?>" alt="" loading="lazy"><?php else: ?><span class="avatar avatar--<?= e($size) ?> avatar--initials" aria-hidden="true"><?= e($initials) ?></span><?php endif; ?>
