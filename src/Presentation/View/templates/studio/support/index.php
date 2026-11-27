<?php
/** @var list<array<string, mixed>> $rooms */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Private rooms</h1>
        <p class="muted">One room per student, for things affecting their studies. Kept, never deleted. To invite a student, open their page in <a href="/studio/students">Students</a>.</p>
        <?php if ($rooms === []): ?>
            <div class="card"><p class="muted">No private rooms yet.</p></div>
        <?php else: ?>
            <ul class="inbox">
                <?php foreach ($rooms as $r): ?>
                    <li class="inbox-row"><a href="/studio/support/<?= e($r['id']) ?>#latest">
                        <span class="inbox-row__top"><span class="inbox-row__subject"><?= e($r['student_name']) ?></span>
                            <span class="muted inbox-row__when"><?= e(date('j M, H:i', (int) strtotime((string) $r['last_message_at']))) ?></span></span>
                        <span class="inbox-row__who muted"><?= e($r['opened_by'] === 'tutor' ? 'You invited them' : 'They opened it') ?> · <?= e($r['message_count']) ?> message<?= (int) $r['message_count'] === 1 ? '' : 's' ?><?= $r['opened_by'] === 'tutor' && $r['guardian_notified_at'] === null ? ' · guardian not yet told' : '' ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
