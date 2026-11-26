<?php
/**
 * @var array<string, mixed> $s
 * @var array<string, string> $modes
 * @var array<string, string> $audiences
 */
$states = ['booked' => 'Booked', 'cancelled' => 'Cancelled by the student', 'done' => 'Done', 'missed' => 'Missed'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/office-hours">Office hours</a></p>
        <h1><?= e(date('l j F, H:i', (int) strtotime((string) $s['starts_at']))) ?>–<?= e(date('H:i', (int) strtotime((string) $s['ends_at']))) ?></h1>
        <p class="muted"><?= e($modes[$s['mode']] ?? $s['mode']) ?> · <?= e($audiences[$s['audience']] ?? $s['audience']) ?> · <?= e($s['booked']) ?>/<?= e($s['capacity']) ?> booked<?= $s['status'] === 'cancelled' ? ' · cancelled' : '' ?><?= $s['note'] ? ' · ' . e($s['note']) : '' ?></p>
        <?php if ($s['bookings'] === []): ?><div class="card"><p class="muted">No bookings yet.</p></div><?php endif; ?>
        <?php foreach ($s['bookings'] as $b): ?>
            <div class="card section-card">
                <h2 class="h3"><?= e($b['full_name']) ?><?= $b['role'] === 'alumni' ? ' <span class="muted">· alumni</span>' : '' ?> <span class="chip"><?= e($states[$b['status']] ?? $b['status']) ?></span></h2>
                <p><?= e($b['question']) ?></p>
                <div class="contact-buttons">
                    <?php if ($b['tel'] !== null): ?><a class="btn btn--small btn--ghost" href="<?= e($b['tel']) ?>">Call</a><a class="btn btn--small btn--ghost" href="<?= e($b['wa']) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                    <a class="btn btn--small btn--ghost" href="/messages/new?to=<?= e($b['user_id']) ?>">Message</a>
                    <?php if ($b['status'] !== 'cancelled'): foreach (['done' => 'Mark done', 'missed' => 'Mark missed'] as $st => $label): if ($b['status'] === $st) { continue; } ?>
                        <form method="post" action="/studio/office-hours/bookings/<?= e($b['id']) ?>" class="inline-form"><?= $csrf->field() ?><input type="hidden" name="status" value="<?= e($st) ?>"><button type="submit" class="btn btn--small"><?= e($label) ?></button></form>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($s['status'] === 'open' && (string) $s['starts_at'] > date('Y-m-d H:i:s')): ?>
            <form method="post" action="/studio/office-hours/<?= e($s['id']) ?>/cancel" class="js-confirm" data-confirm="Cancel this session and tell everyone booked?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--danger">Cancel this session</button></form>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
