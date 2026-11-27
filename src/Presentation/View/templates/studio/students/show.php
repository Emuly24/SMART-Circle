<?php
/**
 * @var array<string, mixed> $s
 * @var array<string, string> $channels
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$types = ['lesson' => 'Watched', 'work' => 'Handed in', 'request' => 'Asked for'];
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/studio/students">Students</a></p>
        <h1><?= e($s['full_name']) ?></h1>
        <p class="muted">Form <?= e($s['form']) ?><?= $s['school_name'] !== null ? ' · ' . e($s['school_name']) : '' ?> · last seen <?= $s['last_login_at'] !== null ? e(date('j M Y, H:i', (int) strtotime((string) $s['last_login_at']))) : 'never' ?></p>
        <?php if ($s['flags'] !== []): ?>
            <ul class="flags"><?php foreach ($s['flags'] as $f): ?><li class="flag flag--<?= e($f['key']) ?>"><?= e($f['label']) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="card section-card">
                <h2 class="h3">Get in touch</h2>
                <p class="field__label">Student · <?= e(\SmartCircle\Support\PhoneNumber::display((string) $s['phone'])) ?></p>
                <div class="contact-buttons">
                    <a class="btn btn--small" href="/messages/new?to=<?= e($s['id']) ?>">Message in SMART Circle</a>
                    <?php foreach ($s['links']['student'] as $l): ?><a class="btn btn--small btn--ghost" href="<?= e($l['href']) ?>"<?= str_starts_with($l['href'], 'https:') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l['label']) ?></a><?php endforeach; ?>
                </div>
                <p class="field__label">Guardian<?= !empty($s['guardian_name']) ? ' · ' . e($s['guardian_name']) : '' ?><?= !empty($s['guardian_phone']) ? ' · ' . e(\SmartCircle\Support\PhoneNumber::display((string) $s['guardian_phone'])) : '' ?></p>
                <div class="contact-buttons">
                    <?php foreach ($s['links']['guardian'] as $l): ?><a class="btn btn--small btn--ghost" href="<?= e($l['href']) ?>"<?= str_starts_with($l['href'], 'https:') ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l['label']) ?></a><?php endforeach; ?>
                    <?php if ($s['links']['guardian'] === []): ?><span class="muted">No guardian number on file.</span><?php endif; ?>
                </div>
            </div>
            <div class="card section-card">
                <h2 class="h3">This term</h2>
                <dl class="stat-row">
                    <div><dt>Lessons (14 days)</dt><dd><?= e($s['lessons_14d']) ?></dd></div>
                    <div><dt>Work (30 days)</dt><dd><?= e($s['work_30d']) ?></dd></div>
                    <div><dt>Missed (30 days)</dt><dd><?= e($s['missed_30d']) ?></dd></div>
                    <div><dt>Open requests</dt><dd><?= e($s['open_requests']) ?></dd></div>
                </dl>
                <?php if ($s['grades'] !== null && $s['grades']['overall'] !== null): ?>
                    <p>Term average <strong><?= e($s['grades']['overall']) ?>%</strong><?= $s['grades']['overall_grade'] !== null ? ' (' . e($s['grades']['overall_grade']['grade']) . ')' : '' ?> across <?= e($s['grades']['graded']) ?> of <?= e($s['grades']['subject_count']) ?> subjects.
                        <a href="/studio/grades/student/<?= e($s['id']) ?>">See grades</a></p>
                <?php else: ?><p class="muted">No marked work this term yet.</p><?php endif; ?>
            </div>
        </div>

        <div class="card section-card">
            <h2 class="h3">Recent activity</h2>
            <?php if ($s['timeline'] === []): ?><p class="muted">Nothing yet.</p><?php else: ?>
                <ul class="timeline"><?php foreach ($s['timeline'] as $a): ?>
                    <li><span class="muted"><?= e(date('j M', (int) strtotime((string) $a['at']))) ?></span> <?= e($types[$a['type']] ?? $a['type']) ?>: <?= e($a['title']) ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="contact-log">
            <h2 class="h3">Contact log</h2>
            <p class="muted">After a call or visit, write a line about what you discussed. Kept for your records; the student does not see it.</p>
            <form method="post" action="/studio/students/<?= e($s['id']) ?>/contact" class="form">
                <?= $csrf->field() ?>
                <div class="field-row">
                    <label class="field"><span class="field__label">With</span><select name="who"><option value="student">The student</option><option value="guardian" <?= ($old['who'] ?? '') === 'guardian' ? 'selected' : '' ?>>The guardian</option></select></label>
                    <label class="field"><span class="field__label">How</span><select name="channel" required><option value="">Choose…</option>
                        <?php foreach ($channels as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['channel'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('channel') ?></label>
                </div>
                <label class="field"><span class="field__label">What you discussed</span><textarea name="summary" rows="2" maxlength="450" required><?= e($old['summary'] ?? '') ?></textarea><?= $err('summary') ?></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save to log</button></div>
            </form>
            <?php if ($s['contacts'] !== []): ?>
                <ul class="timeline"><?php foreach ($s['contacts'] as $c): ?>
                    <li><span class="muted"><?= e(date('j M Y', (int) strtotime((string) $c['created_at']))) ?> · <?= e($channels[$c['channel']] ?? $c['channel']) ?></span> <?= e($c['summary']) ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="private-room">
            <h2 class="h3">Private room</h2>
            <p class="muted">Invite <?= e(explode(' ', (string) $s['full_name'])[0]) ?> to talk privately about something affecting their studies. They are notified; you will be asked to tell the guardian that you invited them (not what it is about).</p>
            <form method="post" action="/studio/students/<?= e($s['id']) ?>/invite" class="form">
                <?= $csrf->field() ?>
                <label class="field"><span class="field__label">First message</span><textarea name="body" rows="3" maxlength="4000" required></textarea></label>
                <div class="form-actions"><button type="submit" class="btn btn--small btn--green">Invite to the private room</button></div>
            </form>
        </div>
    </div>
</section>
