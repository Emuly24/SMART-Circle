<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var list<array<string, mixed>> $ready
 * @var array{form: int, subject: string, status: string} $filter
 * @var array<string, string> $names
 * @var array<string, string> $short
 * @var array<string, string> $statuses
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <div class="title-row">
            <h1>Topic requests</h1>
            <div class="title-row__actions"><a class="btn btn--small btn--ghost" href="/studio/topics">Edit topic catalogue</a></div>
        </div>
        <p class="lead">Ranked honestly: first by students whose own school has taught the topic, then by how many of all students’ schools have taught it (readiness), then by all voices.</p>
        <p class="note">Early-access requests and students’ coverage reports wait in <a href="/studio/checks">Checks</a>.</p>

        <form method="get" action="/studio/requests" class="filters">
            <label>Form
                <select name="form">
                    <option value="0">All forms</option>
                    <?php for ($f = 1; $f <= 4; $f++): ?><option value="<?= $f ?>" <?= $filter['form'] === $f ? 'selected' : '' ?>>Form <?= $f ?></option><?php endfor; ?>
                </select>
            </label>
            <label>Subject
                <select name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($names as $k => $n): ?><option value="<?= e($k) ?>" <?= $filter['subject'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Show
                <select name="status">
                    <?php foreach (['active' => 'Waiting and planned', 'open' => 'Waiting', 'planned' => 'Planned', 'recorded' => 'Lesson ready', 'closed' => 'Closed', 'all' => 'Everything'] as $k => $n): ?>
                        <option value="<?= e($k) ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= e($n) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn--small">Filter</button>
        </form>

        <?php if ($ready !== []): ?>
            <div class="card ready-card">
                <h2 class="h3">Ready to record, no lesson yet</h2>
                <p class="muted">Most students who study these have been taught them at school. Recording now helps before anyone gets stuck.</p>
                <ul class="ready-list">
                    <?php foreach ($ready as $t): ?>
                        <li><span class="badge badge--<?= e($t['subject']) ?>"><?= e($names[$t['subject']] ?? $t['subject']) ?></span>
                            <span>Form <?= e($t['form']) ?> · <?= e($t['title']) ?></span>
                            <span class="readiness"><?= e($t['readiness']['ready']) ?>/<?= e($t['readiness']['eligible']) ?> taught</span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <div class="card"><p>No requests here yet.</p></div>
        <?php else: ?>
            <ol class="request-list">
                <?php foreach ($rows as $i => $r): ?>
                    <li class="request-row<?= (int) $r['taught_votes'] === 0 ? ' request-row--untaught' : '' ?>">
                        <span class="request-row__votes" aria-label="<?= e($r['taught_votes']) ?> of <?= e($r['votes']) ?> students taught at school"><strong><?= e($r['taught_votes']) ?></strong><small>taught</small><small class="request-row__all">of <?= e($r['votes']) ?></small></span>
                        <a class="request-row__main" href="/studio/requests/<?= e($r['id']) ?>">
                            <span class="request-row__meta">
                                <span class="badge badge--<?= e($r['subject']) ?>"><?= e($names[$r['subject']] ?? $r['subject']) ?></span>
                                <span class="muted">Form <?= e($r['form']) ?></span>
                                <span class="readiness" title="Students who study this and whose school has taught it">Readiness <?= e($r['readiness']['ready']) ?>/<?= e($r['readiness']['eligible']) ?></span>
                                <span class="chip chip--<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
                            </span>
                            <span class="request-row__title"><?= $r['parent_title'] ? '<span class="muted">' . e($r['parent_title']) . ' › </span>' : '' ?><?= e($r['topic_title']) ?></span>
                            <span class="struggle-bar">
                                <?php foreach ($r['struggles'] as $type => $n): ?>
                                    <span class="struggle struggle--<?= e($type) ?>"><?= e($short[$type] ?? $type) ?> <b><?= e($n) ?></b></span>
                                <?php endforeach; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
