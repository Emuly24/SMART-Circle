<?php
/**
 * @var list<array<string, mixed>> $rows
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
        <p class="lead">Ranked by how many students are stuck. Exceptions (topics their school has not taught yet) wait at the top for your decision.</p>

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

        <?php if ($rows === []): ?>
            <div class="card"><p>No requests here yet.</p></div>
        <?php else: ?>
            <ol class="request-list">
                <?php foreach ($rows as $i => $r): ?>
                    <li class="request-row<?= $r['exception_status'] === 'pending' ? ' request-row--exception' : '' ?>">
                        <span class="request-row__votes" aria-label="<?= e($r['votes']) ?> students"><strong><?= e($r['votes']) ?></strong><small><?= (int) $r['votes'] === 1 ? 'voice' : 'voices' ?></small></span>
                        <a class="request-row__main" href="/studio/requests/<?= e($r['id']) ?>">
                            <span class="request-row__meta">
                                <span class="badge badge--<?= e($r['subject']) ?>"><?= e($names[$r['subject']] ?? $r['subject']) ?></span>
                                <span class="muted">Form <?= e($r['form']) ?></span>
                                <?php if ($r['exception_status'] === 'pending'): ?><span class="tag">Exception: decide</span><?php endif; ?>
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
