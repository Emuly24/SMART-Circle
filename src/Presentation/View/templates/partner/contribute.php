<?php
/**
 * @var array<string, mixed> $p the partner (school_id, school_name, full_name, subject_list)
 * @var array<string, mixed>|null $calendar year, latest
 * @var list<array<string, mixed>> $resources
 * @var list<array<string, mixed>> $suggestions
 * @var array<string, string> $kinds
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $mainTopics
 * @var bool $canWhatsapp
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$s = (array) ($old['s'] ?? []);
$latest = $calendar['latest'] ?? null;
$states = ['pending' => ['Waiting for the tutor', 'chip--open'], 'added' => ['Added to the syllabus', 'chip--recorded'], 'declined' => ['Not added', 'chip--closed']];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="eyebrow"><?= e($p['school_name']) ?></p>
        <h1>Help keep SMART Circle accurate</h1>
        <p class="lead">Your knowledge of your school keeps lessons honest and on time. Thank you.</p>

        <div class="card section-card" id="calendar">
            <h2 class="h3">1. Is your school’s calendar right?</h2>
            <?php if ($calendar === null): ?>
                <p class="muted">The tutor has not entered this year’s terms yet.</p>
            <?php else: ?>
                <p>Please look at the <a href="/partner/calendar"><?= e($calendar['year']['name']) ?> calendar</a>: term dates, your school’s mid-term break and exam weeks.</p>
                <?php if ($latest !== null): ?>
                    <p><span class="chip <?= $latest['status'] === 'confirmed' ? 'chip--recorded' : ($latest['resolved_at'] ? 'chip--recorded' : 'chip--open') ?>"><?= e($latest['status'] === 'confirmed' ? 'Confirmed' : ($latest['resolved_at'] ? 'Problem corrected' : 'Problem reported')) ?></span>
                        <span class="muted">by <?= e($latest['full_name'] ?? 'a teacher') ?>, <?= e(date('j M Y', (int) strtotime((string) $latest['created_at']))) ?></span></p>
                <?php endif; ?>
                <form method="post" action="/partner/contribute/calendar" class="form">
                    <?= $csrf->field() ?>
                    <label class="field"><span class="field__label">If something is wrong, what should it be? <span class="muted">(leave empty if it is right)</span></span>
                        <textarea name="note" rows="2" maxlength="1000"><?= e($old['note'] ?? '') ?></textarea><?= $err('calendar') ?></label>
                    <div class="form-actions decision__buttons">
                        <button type="submit" name="answer" value="correct" class="btn btn--small btn--green">Yes, our calendar is correct</button>
                        <button type="submit" name="answer" value="problem" class="btn btn--small btn--ghost">Something is wrong</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="resources">
            <h2 class="h3">2. Share a resource</h2>
            <p class="muted">Your syllabus, scheme of work, past papers or notes help the tutor teach exactly what your students meet in class.</p>
            <form method="post" action="/partner/contribute/resource" enctype="multipart/form-data" class="form">
                <?= $csrf->field() ?>
                <div class="field-row">
                    <label class="field"><span class="field__label">What is it?</span>
                        <select name="kind" required><option value="">Choose…</option><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('kind') ?></label>
                    <label class="field"><span class="field__label">Name</span><input type="text" name="title" maxlength="150" required value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. Form 3 Chemistry scheme of work, Term 1"><?= $err('title') ?></label>
                </div>
                <div class="field-row">
                    <label class="field"><span class="field__label">Subject <span class="muted">(optional)</span></span>
                        <select name="subject"><option value="">Any</option><?php foreach ($names as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('subject') ?></label>
                    <label class="field"><span class="field__label">Form <span class="muted">(optional)</span></span>
                        <select name="form"><option value="">Any</option><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (string) ($old['form'] ?? '') === (string) $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('form') ?></label>
                </div>
                <label class="field"><span class="field__label">File <span class="muted">(PDF, Word or photo, up to 9 MB)</span></span>
                    <input type="file" name="file" accept=".pdf,.doc,.docx,image/jpeg,image/png"><?= $err('file') ?></label>
                <label class="field"><span class="field__label">Note <span class="muted">(optional)</span></span><input type="text" name="note" maxlength="500" value="<?= e($old['note'] ?? '') ?>"></label>
                <div class="form-actions decision__buttons">
                    <button type="submit" name="via" value="upload" class="btn btn--small">Upload</button>
                    <?php if ($canWhatsapp): ?><button type="submit" name="via" value="whatsapp" class="btn btn--small btn--green">Send on WhatsApp instead</button><?php endif; ?>
                </div>
                <?php if ($canWhatsapp): ?><p class="field__hint">“Send on WhatsApp” opens WhatsApp with a message to the tutor ready; attach the file or photos there.</p><?php endif; ?>
            </form>
            <?php if ($resources !== []): ?>
                <h3 class="h3">Shared from <?= e($p['school_name']) ?></h3>
                <ul class="timeline"><?php foreach ($resources as $r): ?>
                    <li><span class="muted"><?= e(date('j M', (int) strtotime((string) $r['created_at']))) ?></span>
                        <?php if ($r['file_path']): ?><a href="/partner/resources/<?= e($r['id']) ?>/file"><?= e($r['title']) ?></a><?php else: ?><?= e($r['title']) ?><?php endif; ?>
                        <span class="muted">· <?= e($kinds[$r['kind']] ?? $r['kind']) ?> · <?= e($r['via'] === 'whatsapp' ? 'on WhatsApp' : 'uploaded') ?><?= $r['status'] === 'reviewed' ? ' · seen by the tutor' : '' ?></span></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="suggest">
            <h2 class="h3">3. Is a topic missing?</h2>
            <p class="muted">The topic list was drafted from the national syllabus. If your syllabus has something we missed, suggest it; the tutor adds it.</p>
            <form method="post" action="/partner/contribute/suggest" class="form">
                <?= $csrf->field() ?>
                <div class="field-row">
                    <label class="field"><span class="field__label">Form</span>
                        <select name="form" required><option value="">Choose…</option><?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= (string) ($s['form'] ?? '') === (string) $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?></select><?= $err('s_form') ?></label>
                    <label class="field"><span class="field__label">Subject</span>
                        <select name="subject" required><option value="">Choose…</option><?php foreach ($names as $k => $l): ?><option value="<?= e($k) ?>" <?= ($s['subject'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('s_subject') ?></label>
                </div>
                <label class="field"><span class="field__label">Missing topic</span><input type="text" name="title" maxlength="200" required value="<?= e($s['title'] ?? '') ?>"><?= $err('s_title') ?></label>
                <label class="field"><span class="field__label">Part of an existing topic? <span class="muted">(optional)</span></span>
                    <select name="parent_id"><option value="">No, it is a main topic</option>
                        <?php foreach ($mainTopics as $t): ?><option value="<?= e($t['id']) ?>" <?= (string) ($s['parent_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>>Form <?= e($t['form']) ?> <?= e($names[$t['subject']] ?? $t['subject']) ?>: <?= e($t['label']) ?></option><?php endforeach; ?>
                    </select><?= $err('s_parent_id') ?></label>
                <label class="field"><span class="field__label">Where it appears <span class="muted">(optional)</span></span><input type="text" name="note" maxlength="1000" value="<?= e($s['note'] ?? '') ?>" placeholder="e.g. Syllabus page 23, taught in Term 2"></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Suggest it</button></div>
            </form>
            <?php if ($suggestions !== []): ?>
                <ul class="timeline"><?php foreach ($suggestions as $g): [$label, $chip] = $states[$g['status']] ?? [$g['status'], 'chip']; ?>
                    <li>Form <?= e($g['form']) ?> <?= e(\SmartCircle\Support\Subjects::NAMES[$g['subject']] ?? $g['subject']) ?>: <strong><?= e($g['title']) ?></strong> <span class="chip <?= e($chip) ?>"><?= e($label) ?></span><?= $g['decision_note'] ? ' <span class="muted">' . e($g['decision_note']) . '</span>' : '' ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </div>
</section>
