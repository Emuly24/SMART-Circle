<?php
/**
 * @var list<array<string, mixed>> $students
 * @var list<array<string, mixed>> $schools
 * @var array<string, string> $actions
 * @var array<int, string> $errors
 * @var array<string, mixed> $old
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>End of the school year</h1>
        <p class="lead">When MANEB is over and the new year begins, move each student on. Students moving into Form 3 need their track; Form 4 students who finished join the alumni (they keep their account, results and messages).</p>
        <?php if ($errors !== []): ?><div class="alert alert--error" role="alert">Some choices need fixing (marked below). Nothing was changed.</div><?php endif; ?>
        <?php if ($students === []): ?><div class="card"><p class="muted">No active students.</p></div><?php else: ?>
            <form method="post" action="/studio/year-end" class="form js-confirm" data-confirm="Apply these changes for every student?">
                <?= $csrf->field() ?>
                <ul class="student-list">
                    <?php foreach ($students as $s): $id = (string) $s['id']; $o = (array) ($old[$id] ?? []);
                        $act = (string) ($o['action'] ?? \SmartCircle\Application\Alumni\YearEndService::suggested((int) $s['form'])); ?>
                        <li class="card student-row">
                            <h2 class="h3"><?= e($s['full_name']) ?> <span class="muted">· Form <?= e($s['form']) ?><?= $s['school_name'] !== null ? ' · ' . e($s['school_name']) : '' ?></span></h2>
                            <div class="field-row">
                                <label class="field"><span class="field__label">Next year</span>
                                    <select name="s[<?= e($id) ?>][action]">
                                        <?php foreach ($actions as $k => $l): if (($k === 'graduate') !== ((int) $s['form'] === 4) && $k !== 'stay') { continue; } ?>
                                            <option value="<?= e($k) ?>" <?= $act === $k ? 'selected' : '' ?>><?= e($k === 'promote' ? 'Moves up to Form ' . ((int) $s['form'] + 1) : $l) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                <?php if ((int) $s['form'] === 2): ?>
                                    <label class="field"><span class="field__label">Form 3 track</span>
                                        <select name="s[<?= e($id) ?>][track]"><option value="">Choose…</option>
                                            <option value="science" <?= ($o['track'] ?? '') === 'science' ? 'selected' : '' ?>>Science (Physics and Chemistry)</option>
                                            <option value="humanities" <?= ($o['track'] ?? '') === 'humanities' ? 'selected' : '' ?>>Humanities</option></select></label>
                                    <label class="field"><span class="field__label">New school <span class="muted">(if selected elsewhere)</span></span>
                                        <select name="s[<?= e($id) ?>][school_id]"><option value="">Same school</option>
                                            <?php foreach ($schools as $sc): ?><option value="<?= e($sc['id']) ?>" <?= (string) ($o['school_id'] ?? '') === (string) $sc['id'] ? 'selected' : '' ?>><?= e($sc['name']) ?></option><?php endforeach; ?></select></label>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($errors[(int) $s['id']])): ?><p class="field__error"><?= e($errors[(int) $s['id']]) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="form-actions form-actions--sticky"><button type="submit" class="btn">Apply for every student</button></div>
            </form>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
