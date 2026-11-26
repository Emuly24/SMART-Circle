<?php
/**
 * @var list<array<string, mixed>> $recipients
 * @var array<string, string> $audiences
 * @var bool $isTutor
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$roles = ['student' => 'Students', 'alumni' => 'Alumni', 'partner' => 'Partner teachers', 'tutor' => 'Tutors'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/messages">Messages</a></p>
        <h1><?= $isTutor ? 'New message' : 'Write to the tutor' ?></h1>
        <form method="post" action="/messages" class="form card">
            <?= $csrf->field() ?>
            <label class="field"><span class="field__label">To</span>
                <select name="to" required>
                    <?php if ($isTutor): ?><option value="">Choose…</option><?php endif; ?>
                    <?php foreach ($roles as $r => $label): $group = array_filter($recipients, static fn (array $p): bool => $p['role'] === $r); if ($group === []) { continue; } ?>
                        <optgroup label="<?= e($label) ?>">
                            <?php foreach ($group as $p): ?><option value="<?= e($p['id']) ?>" <?= (string) ($old['to'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select><?= $err('to') ?></label>
            <label class="field"><span class="field__label">Subject</span><input type="text" name="subject" maxlength="150" required value="<?= e(($old['mode'] ?? '') === 'announce' ? '' : ($old['subject'] ?? '')) ?>"><?= ($old['mode'] ?? '') === 'announce' ? '' : $err('subject') ?></label>
            <label class="field"><span class="field__label">Message</span><textarea name="body" rows="6" maxlength="4000" required><?= e(($old['mode'] ?? '') === 'announce' ? '' : ($old['body'] ?? '')) ?></textarea><?= ($old['mode'] ?? '') === 'announce' ? '' : $err('body') ?>
                <span class="field__hint">Messages are kept and cannot be deleted<?= $isTutor ? '' : '. Students can only write to the tutor, not to each other' ?>.</span></label>
            <div class="form-actions"><button type="submit" class="btn btn--small">Send</button></div>
        </form>
        <?php if ($isTutor): $a = ($old['mode'] ?? '') === 'announce'; ?>
            <details class="card section-card"<?= $a ? ' open' : '' ?> id="announce">
                <summary class="h3">Send an announcement</summary>
                <p class="muted">Goes to everyone in the group as it is now. They can read it but not reply to everyone; each person can write to you privately.</p>
                <form method="post" action="/messages/announce" class="form">
                    <?= $csrf->field() ?>
                    <label class="field"><span class="field__label">To</span>
                        <select name="audience" required><option value="">Choose…</option>
                            <?php foreach ($audiences as $k => $l): ?><option value="<?= e($k) ?>" <?= $a && ($old['audience'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select><?= $a ? $err('audience') : '' ?></label>
                    <label class="field"><span class="field__label">Subject</span><input type="text" name="subject" maxlength="150" required value="<?= e($a ? ($old['subject'] ?? '') : '') ?>"><?= $a ? $err('subject') : '' ?></label>
                    <label class="field"><span class="field__label">Announcement</span><textarea name="body" rows="5" maxlength="4000" required><?= e($a ? ($old['body'] ?? '') : '') ?></textarea><?= $a ? $err('body') : '' ?></label>
                    <div class="form-actions"><button type="submit" class="btn btn--small btn--green">Send announcement</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>
