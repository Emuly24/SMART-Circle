<?php
/**
 * @var array<string, mixed> $calendar year, schools
 * @var list<array<string, mixed>> $resources
 * @var list<array<string, mixed>> $suggestions
 * @var array<string, string> $kinds
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>From partner teachers</h1>

        <div class="card section-card" id="calendar">
            <h2 class="h3">School calendars<?= $calendar['year'] !== null ? ' · ' . e($calendar['year']['name']) : '' ?></h2>
            <?php if ($calendar['schools'] === []): ?><p class="muted">No partner schools yet.</p><?php else: ?>
                <ul class="timeline"><?php foreach ($calendar['schools'] as $c): ?>
                    <li><strong><?= e($c['school_name']) ?></strong>
                        <?php if ($c['id'] === null): ?><span class="chip">Not checked yet</span>
                        <?php elseif ($c['status'] === 'confirmed'): ?><span class="chip chip--recorded">Confirmed</span> <span class="muted"><?= e($c['full_name'] ?? '') ?>, <?= e(date('j M', (int) strtotime((string) $c['created_at']))) ?></span>
                        <?php else: ?><span class="chip <?= $c['resolved_at'] ? 'chip--recorded' : 'chip--open' ?>"><?= $c['resolved_at'] ? 'Corrected' : 'Problem reported' ?></span> <?= e($c['note'] ?? '') ?>
                            <?php if (!$c['resolved_at']): ?> <a href="/studio/calendar">Fix the calendar</a>
                                <form method="post" action="/studio/contributions/calendar/<?= e($c['id']) ?>/resolve" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Mark corrected</button></form><?php endif; ?>
                        <?php endif; ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="resources">
            <h2 class="h3">Resources</h2>
            <?php if ($resources === []): ?><p class="muted">Nothing shared yet.</p><?php else: ?>
                <ul class="timeline"><?php foreach ($resources as $r): ?>
                    <li><?= $r['status'] === 'new' ? '<span class="chip chip--open">New</span> ' : '' ?>
                        <?php if ($r['file_path']): ?><a href="/studio/resources/<?= e($r['id']) ?>/file"><?= e($r['title']) ?></a><?php else: ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
                        <span class="muted">· <?= e($kinds[$r['kind']] ?? $r['kind']) ?><?= $r['form'] ? ' · Form ' . e($r['form']) : '' ?><?= $r['subject'] ? ' · ' . e($names[$r['subject']] ?? $r['subject']) : '' ?> · <?= e($r['school_name'] ?? '') ?>, <?= e($r['full_name'] ?? '') ?> · <?= e($r['via'] === 'whatsapp' ? 'sent on WhatsApp' : 'uploaded') ?></span>
                        <?= $r['note'] ? '<br><span class="muted">' . e($r['note']) . '</span>' : '' ?>
                        <?php if ($r['status'] === 'new'): ?><form method="post" action="/studio/contributions/resources/<?= e($r['id']) ?>/reviewed" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Seen</button></form><?php endif; ?></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <div class="card section-card" id="suggestions">
            <h2 class="h3">Suggested topics</h2>
            <?php if ($suggestions === []): ?><p class="muted">No suggestions yet.</p><?php endif; ?>
            <?php foreach ($suggestions as $g): ?>
                <div class="suggestion">
                    <p><strong>Form <?= e($g['form']) ?> <?= e($names[$g['subject']] ?? $g['subject']) ?>:</strong> <?= e($g['title']) ?><?= $g['parent_title'] ? ' <span class="muted">(under “' . e($g['parent_title']) . '”)</span>' : '' ?>
                        <span class="muted">· <?= e($g['school_name'] ?? '') ?>, <?= e($g['full_name'] ?? '') ?></span></p>
                    <?php if ($g['note']): ?><p class="muted"><?= e($g['note']) ?></p><?php endif; ?>
                    <?php if ($g['status'] === 'pending'): ?>
                        <form method="post" action="/studio/contributions/suggestions/<?= e($g['id']) ?>" class="form">
                            <?= $csrf->field() ?>
                            <div class="field-row">
                                <label class="field"><span class="field__label">Topic title <span class="muted">(you can reword it)</span></span><input type="text" name="title" maxlength="200" value="<?= e($g['title']) ?>"></label>
                                <label class="field"><span class="field__label">Note to the teacher <span class="muted">(optional)</span></span><input type="text" name="note" maxlength="500"></label>
                            </div>
                            <div class="form-actions decision__buttons">
                                <button type="submit" name="decision" value="add" class="btn btn--small btn--green">Add to the catalogue</button>
                                <button type="submit" name="decision" value="decline" class="btn btn--small btn--ghost">Decline</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p><span class="chip <?= $g['status'] === 'added' ? 'chip--recorded' : 'chip--closed' ?>"><?= $g['status'] === 'added' ? 'Added' : 'Declined' ?></span>
                            <?php if ($g['topic_id']): ?><a href="/studio/topics/<?= e($g['topic_id']) ?>">Open the topic</a><?php endif; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
