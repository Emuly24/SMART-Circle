<?php
/**
 * @var list<array<string, mixed>> $people
 * @var array<string, string> $doing
 * @var array<string, string> $needs
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Alumni</h1>
        <p class="muted"><a href="/alumni/community">Community board</a> · <a href="/studio/year-end">End of the year</a> · <a href="/messages/new">Announce to all alumni</a></p>
        <?php if ($people === []): ?><div class="card"><p class="muted">No alumni yet. Students join the alumni from the end-of-year page after Form 4.</p></div><?php else: ?>
            <ul class="student-list">
                <?php foreach ($people as $a): $n = array_filter(explode(',', (string) $a['needs'])); ?>
                    <li class="card student-row">
                        <h2 class="h3"><?= e($a['full_name']) ?> <span class="muted">· class of <?= e($a['left_year']) ?></span></h2>
                        <p><?= e($doing[$a['now_doing'] ?? ''] ?? 'Not told us yet') ?><?= $a['institution'] ? ' · ' . e($a['institution']) : '' ?><?= $a['programme'] ? ' · ' . e($a['programme']) : '' ?></p>
                        <?php if ($n !== []): ?><ul class="flags"><?php foreach ($n as $k): ?><li class="flag"><?= e($needs[$k] ?? $k) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if ((bool) $a['can_mentor']): ?><p><span class="chip chip--recorded">Can mentor</span> <?= e($a['mentor_note'] ?? '') ?></p><?php endif; ?>
                        <div class="contact-buttons">
                            <a class="btn btn--small" href="/messages/new?to=<?= e($a['user_id']) ?>">Message</a>
                            <?php $ph = \SmartCircle\Support\PhoneNumber::normalize((string) $a['phone']); if ($ph !== null): ?>
                                <a class="btn btn--small btn--ghost" href="tel:<?= e($ph) ?>">Call</a>
                                <a class="btn btn--small btn--ghost" href="https://wa.me/<?= e(ltrim($ph, '+')) ?>" target="_blank" rel="noopener">WhatsApp</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
