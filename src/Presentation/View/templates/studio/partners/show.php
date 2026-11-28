<?php
/**
 * @var array<string, mixed> $partner
 * @var string|null $code
 * @var list<array<string, mixed>> $letters
 * @var array<string, array{covered: int, total: int}> $progress
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$id = (int) $partner['id'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/partners">School partners</a></p>
        <h1><?= e($partner['full_name']) ?></h1>
        <p class="lead"><?= e($partner['school_name'] ?? '') ?>, <?= e($partner['district'] ?? '') ?></p>
        <?= $view->partial('title_form', ['id' => $id, 'current' => $partner['title'] ?? null, 'back' => '/studio/partners/' . $id]) ?>

        <?php if ($code !== null): ?>
            <div class="card setup-code">
                <h2 class="h3">Setup code</h2>
                <p class="reference"><?= e(substr($code, 0, 4) . ' ' . substr($code, 4)) ?></p>
                <p>Give this code to <?= e(explode(' ', (string) $partner['full_name'])[0]) ?> with the letter. They open the website, choose <strong>Set up my account</strong>, and enter their phone number (<?= e($partner['phone']) ?>) and this code. It works once, for 30 days.</p>
                <div class="setup-code__actions">
                    <form method="post" action="/studio/partners/<?= e($id) ?>/code/hide"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">I have given it, hide the code</button></form>
                </div>
            </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="card">
                <h2 class="h3">Details</h2>
                <dl class="facts">
                    <dt>Phone</dt><dd><?= e($partner['phone']) ?></dd>
                    <?php if ($partner['email']): ?><dt>Email</dt><dd><?= e($partner['email']) ?></dd><?php endif; ?>
                    <dt>Subjects</dt><dd><?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $partner['subject_list']))) ?></dd>
                    <dt>Account</dt><dd><?= $partner['status'] === 'active' ? ($partner['last_login_at'] ? 'Active, last login ' . e(date('j M Y', strtotime((string) $partner['last_login_at']) ?: time())) : 'Not set up yet') : 'Disabled' ?></dd>
                </dl>
            </div>
            <div class="card">
                <h2 class="h3">Letters</h2>
                <ul class="letter-list">
                    <?php foreach ($letters as $l): ?>
                        <li><a href="/letters/<?= e($l['code']) ?>"><?= e($l['title']) ?></a> <span class="muted">· <?= e($l['code']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2 class="h3">What the school has taught</h2>
            <?= $view->partial('coverage_progress', ['progress' => $progress, 'names' => $names, 'subjects' => $partner['subject_list']]) ?>
            <p><a class="btn btn--small btn--ghost" href="/studio/schools/<?= e($partner['school_id']) ?>">Open the school</a></p>
        </div>

        <div class="danger-zone">
            <form method="post" action="/studio/partners/<?= e($id) ?>/code"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">New setup code</button></form>
            <form method="post" action="/studio/partners/<?= e($id) ?>/status" class="js-confirm" data-confirm="<?= $partner['status'] === 'active' ? 'Disable this account? The teacher will not be able to log in.' : 'Enable this account again?' ?>">
                <?= $csrf->field() ?>
                <input type="hidden" name="status" value="<?= $partner['status'] === 'active' ? 'disabled' : 'active' ?>">
                <button type="submit" class="btn btn--small <?= $partner['status'] === 'active' ? 'btn--danger' : '' ?>"><?= $partner['status'] === 'active' ? 'Disable account' : 'Enable account' ?></button>
            </form>
        </div>
        <div class="card section-card" id="roles">
            <h2 class="h3">Teacher roles</h2>
            <p><strong>Guest teacher:</strong> <?= !empty($partner['guest_teacher']) ? 'yes. They can record lessons in their subjects; you publish each one.' : 'no.' ?></p>
            <form method="post" action="/studio/partners/<?= e($id) ?>/guest" class="inline-form"><?= $csrf->field() ?>
                <input type="hidden" name="guest" value="<?= !empty($partner['guest_teacher']) ? '0' : '1' ?>">
                <button type="submit" class="btn btn--small <?= !empty($partner['guest_teacher']) ? 'btn--ghost' : 'btn--green' ?>"><?= !empty($partner['guest_teacher']) ? 'Stop guest teaching' : 'Make them a guest teacher' ?></button></form>
            <p class="note">Every partner teacher can also <a href="/studio/question-suggestions">suggest questions</a> and check notes and lessons.</p>
            <?php $given = array_filter($contribution ?? []); ?>
            <h3 class="h4">Their contribution</h3>
            <?php if ($given === []): ?><p class="muted">Nothing recorded yet.</p><?php else: ?>
                <ul><?php foreach (\SmartCircle\Application\Partners\ContributionCertificateService::LABELS as $k => $label): ?><?php if (!empty($given[$k])): ?><li><strong><?= e($given[$k]) ?></strong> <?= e($label) ?></li><?php endif; ?><?php endforeach; ?></ul>
                <form method="post" action="/studio/partners/<?= e($id) ?>/certificate" class="inline-form js-confirm" data-confirm="Issue a signed certificate of contribution to this teacher?"><?= $csrf->field() ?><button type="submit" class="btn btn--small">Issue a certificate of contribution</button></form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
