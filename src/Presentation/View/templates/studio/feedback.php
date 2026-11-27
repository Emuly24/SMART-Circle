<?php
/**
 * @var array<string, mixed> $r terms, term_id, summary
 * @var array<string, string> $pace
 * @var int $min
 */
$s = $r['summary'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Student feedback</h1>
        <form method="get" action="/studio/feedback" class="filters"><label>Term
            <select name="term"><?php foreach ($r['terms'] as $t): ?><option value="<?= e($t['id']) ?>" <?= (int) $t['id'] === (int) $r['term_id'] ? 'selected' : '' ?>><?= e($t['year_name']) ?> Term <?= e($t['number']) ?></option><?php endforeach; ?></select></label>
            <button type="submit" class="btn btn--small btn--ghost">Show</button></form>
        <p class="note">Anonymous: no names are stored with answers. Comments and per-form figures only appear once at least <?= e($min) ?> students have answered.</p>
        <?php if ($s['n'] === 0): ?><div class="card"><p class="muted">No answers for this term yet. Students see a short form on their dashboard during the term.</p></div><?php else: ?>
            <div class="stat-tiles">
                <div class="stat-tile"><span class="stat-tile__n"><?= e($s['n']) ?></span><span>answers</span></div>
                <div class="stat-tile"><span class="stat-tile__n"><?= e($s['clarity']) ?>/5</span><span>clarity</span></div>
            </div>
            <div class="card section-card"><h2 class="h3">Pace</h2><ul class="timeline"><?php foreach ($pace as $k => $l): ?><li><?= e($l) ?>: <strong><?= e($s['pace'][$k] ?? 0) ?></strong></li><?php endforeach; ?></ul></div>
            <?php if (!$s['enough']): ?>
                <div class="card"><p class="muted">Comments will show once <?= e($min) ?> or more students have answered, so nobody can be recognised.</p></div>
            <?php else: ?>
                <?php if ($s['forms'] !== []): ?><div class="card section-card"><h2 class="h3">Clarity by form</h2><ul class="timeline"><?php foreach ($s['forms'] as $f => $d): ?><li>Form <?= e($f) ?>: <?= e($d['clarity']) ?>/5 <span class="muted">(<?= e($d['n']) ?> answers)</span></li><?php endforeach; ?></ul></div><?php endif; ?>
                <div class="card section-card"><h2 class="h3">What helps most</h2><?php if ($s['helpful'] === []): ?><p class="muted">None written.</p><?php else: ?><ul><?php foreach ($s['helpful'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
                <div class="card section-card"><h2 class="h3">One thing to change</h2><?php if ($s['change'] === []): ?><p class="muted">None written.</p><?php else: ?><ul><?php foreach ($s['change'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
