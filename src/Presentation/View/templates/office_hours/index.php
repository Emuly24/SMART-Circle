<?php
/**
 * @var list<array<string, mixed>> $slots
 * @var array<string, string> $modes
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>Office hours</h1>
        <p class="lead">Times when the tutor is free to talk. Book a place and say what you are stuck on; the tutor contacts you at that time.</p>
        <?php if ($slots === []): ?><div class="card"><p class="muted">No office hours planned right now. You can always <a href="/messages/new">send the tutor a message</a>.</p></div><?php endif; ?>
        <?php foreach ($slots as $s): $mine = $s['mine']; $booked = $mine !== null && $mine['status'] === 'booked'; $full = (int) $s['booked'] >= (int) $s['capacity']; ?>
            <div class="card section-card slot<?= $booked ? ' slot--mine' : '' ?>" id="slot-<?= e($s['id']) ?>">
                <h2 class="h3"><?= e(date('l j F, H:i', (int) strtotime((string) $s['starts_at']))) ?>–<?= e(date('H:i', (int) strtotime((string) $s['ends_at']))) ?></h2>
                <p class="muted"><?= e($modes[$s['mode']] ?? $s['mode']) ?> · <?= e(max(0, (int) $s['capacity'] - (int) $s['booked'])) ?> of <?= e($s['capacity']) ?> places left<?= $s['note'] ? ' · ' . e($s['note']) : '' ?></p>
                <?php if ($booked): ?>
                    <p><span class="chip chip--recorded">You are booked</span> <?= e($mine['question']) ?></p>
                    <form method="post" action="/office-hours/<?= e($s['id']) ?>/cancel" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Cancel my booking</button></form>
                <?php elseif ($full): ?>
                    <p class="muted">This session is full.</p>
                <?php else: ?>
                    <form method="post" action="/office-hours/<?= e($s['id']) ?>/book" class="form">
                        <?= $csrf->field() ?>
                        <label class="field"><span class="field__label">What would you like help with?</span><input type="text" name="question" maxlength="500" required placeholder="e.g. Balancing redox equations, question 4 of the test"></label>
                        <div class="form-actions"><button type="submit" class="btn btn--small">Book a place</button></div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
