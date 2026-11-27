<?php
/**
 * @var array<string, string> $badges code => earned_at
 * @var array<string, array{0: string, 1: string, 2: string, 3: int}> $allBadges
 * @var list<array<string, mixed>> $challenges
 * @var list<array<string, mixed>> $wall
 * @var array<string, string> $names
 */
$open = array_values(array_filter($challenges, static fn (array $c): bool => strtotime((string) $c['closes_at']) >= time()));
?>
<section class="section section--tight">
    <div class="container">
        <h1>Community</h1>
        <p class="lead">Weekly real-life challenges, the “Spotted in real life” wall, and your badges. Questions about a lesson go under the lesson itself.</p>

        <h2>This week's challenges</h2>
        <?php if ($open === []): ?><div class="card"><p>No open challenges for you right now. New ones come most weeks.</p></div><?php endif; ?>
        <div class="lesson-grid">
            <?php foreach ($challenges as $c): $closed = strtotime((string) $c['closes_at']) < time(); ?>
                <a class="lesson-card challenge-card" href="/community/challenges/<?= e($c['id']) ?>"><span class="lesson-card__meta"><span class="badge badge--<?= e($c['subject']) ?>"><?= e($names[$c['subject']] ?? '') ?></span><span class="lesson-card__form">Form <?= e($c['form']) ?></span></span>
                    <span class="lesson-card__title"><?= e($c['title']) ?></span>
                    <span class="chip<?= $closed ? '' : ' chip--lesson' ?>"><?= $closed ? 'Closed: see the answers' : 'Open until ' . e(date('D j M', (int) strtotime((string) $c['closes_at']))) ?></span></a>
            <?php endforeach; ?>
        </div>

        <h2>Spotted in real life</h2>
        <p><a class="btn btn--small" href="/community/wall">See the wall and add a photo</a></p>
        <?php if ($wall !== []): ?>
            <ul class="wall-grid"><?php foreach ($wall as $p): ?>
                <li><figure class="wall-card<?= $p['featured'] ? ' wall-card--featured' : '' ?>"><img src="/community/wall/<?= e($p['id']) ?>/photo" alt="<?= e($p['caption']) ?>" loading="lazy"><figcaption><?= e($p['caption']) ?><br><span class="muted"><?= e($p['author']) ?></span></figcaption></figure></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>

        <h2>Your badges (<?= e(count($badges)) ?> of <?= e(count($allBadges)) ?>)</h2>
        <p class="muted">Badges are for effort, not marks: everyone can earn every one.</p>
        <ul class="badge-grid">
            <?php foreach ($allBadges as $code => [$name, $how]): $got = isset($badges[$code]); ?>
                <li class="badge-tile <?= $got ? 'badge-tile--earned' : 'badge-tile--locked' ?>"><strong><?= $got ? '🏅 ' : '' ?><?= e($name) ?></strong><span class="muted"><?= e($how) ?></span>
                    <?php if ($got): ?><br><span class="muted">Earned <?= e(date('j M Y', (int) strtotime($badges[$code]))) ?></span><?php endif; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
