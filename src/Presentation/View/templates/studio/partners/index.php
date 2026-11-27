<?php
/**
 * @var list<array<string, mixed>> $partners
 * @var list<array<string, mixed>> $schools
 * @var list<string> $districts
 * @var array<string, string> $names
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$oldSubjects = is_array($old['subjects'] ?? null) ? $old['subjects'] : [];
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>School partners</h1>
        <p class="lead">Teachers who tell us what their school has taught and which textbooks it uses. SMART Circle follows the schools; it never replaces them.</p>
        <p><a class="btn btn--small btn--ghost" href="/studio/invitations?type=teacher_invitation">Write an invitation letter to a teacher</a></p>

        <h2 class="h3">Partners</h2>
        <?php if ($partners === []): ?>
            <div class="card"><p>No partners yet. Invite the first teacher below.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Teacher</th><th>School</th><th>Subjects</th><th>Last report</th></tr></thead>
                    <tbody>
                    <?php foreach ($partners as $p): ?>
                        <tr>
                            <td><a href="/studio/partners/<?= e($p['id']) ?>"><?= e($p['full_name']) ?></a><?= $p['status'] !== 'active' ? ' <span class="tag">Disabled</span>' : '' ?><?= $p['last_login_at'] === null ? ' <span class="chip">Not set up yet</span>' : '' ?></td>
                            <td><?= e($p['school_name'] ?? '') ?><span class="muted">, <?= e($p['district'] ?? '') ?></span></td>
                            <td><?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, array_filter(explode(',', (string) $p['subjects']))))) ?></td>
                            <td><?= $p['last_report'] ? e(date('j M Y', strtotime((string) $p['last_report']) ?: time())) : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="/studio/partners" class="form card partner-invite" id="invite">
            <?= $csrf->field() ?>
            <h2 class="h3">Invite a teacher</h2>
            <p class="muted">Creates the account, a one-time setup code to give the teacher, and a printable invitation letter for the school.</p>
            <div class="field-row">
                <label class="field"><span class="field__label">Teacher’s full name</span><input type="text" name="full_name" value="<?= e($old['full_name'] ?? '') ?>" maxlength="150" required><?= $err('full_name') ?></label>
                <label class="field"><span class="field__label">Phone</span><input type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>" inputmode="tel" required><?= $err('phone') ?></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">School</span><input type="text" name="school_name" value="<?= e($old['school_name'] ?? '') ?>" list="school-list" maxlength="150" required><?= $err('school_name') ?></label>
                <label class="field"><span class="field__label">District</span>
                    <select name="district" required>
                        <option value="">Choose…</option>
                        <?php foreach ($districts as $d): ?><option <?= ($old['district'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                    </select><?= $err('district') ?>
                </label>
            </div>
            <datalist id="school-list">
                <?php foreach ($schools as $s): ?><option value="<?= e($s['name']) ?>"><?= e($s['district']) ?></option><?php endforeach; ?>
            </datalist>
            <label class="field"><span class="field__label">Email <span class="muted">(optional)</span></span><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" maxlength="190"><?= $err('email') ?></label>
            <fieldset class="field">
                <legend class="field__label">Subjects they teach</legend>
                <div class="choice-grid">
                    <?php foreach ($names as $k => $n): ?>
                        <label class="choice"><input type="checkbox" name="subjects[]" value="<?= e($k) ?>" <?= in_array($k, $oldSubjects, true) ? 'checked' : '' ?>> <?= e($n) ?></label>
                    <?php endforeach; ?>
                </div>
                <?= $err('subjects') ?>
            </fieldset>
            <div class="form-actions"><button type="submit" class="btn">Create partner account</button></div>
        </form>

        <h2 class="h3">Schools</h2>
        <p class="muted">Every school our students and partners come from. Open one to enter what it has taught (for example when a teacher tells you by phone).</p>
        <?php if ($schools === []): ?>
            <div class="card"><p>No schools yet. They appear when students apply or partners are invited.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>School</th><th>Students</th><th>Partners</th><th>Topics reported</th></tr></thead>
                    <tbody>
                    <?php foreach ($schools as $s): ?>
                        <tr>
                            <td><a href="/studio/schools/<?= e($s['id']) ?>"><?= e($s['name']) ?></a><span class="muted">, <?= e($s['district']) ?></span></td>
                            <td><?= e($s['students']) ?></td>
                            <td><?= e($s['partners']) ?></td>
                            <td><?= e($s['coverage_rows']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
