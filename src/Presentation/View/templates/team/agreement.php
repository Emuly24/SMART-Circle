<?php
/**
 * @var array<string, mixed> $t
 * @var array<string, string> $names
 * @var string $philosophy
 * @var list<array{0: string, 1: string}> $principles
 * @var list<string> $duties
 * @var string|null $error
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="eyebrow">Joining the SMART Circle Team</p>
        <h1>Before you begin</h1>
        <p class="lead">You have been invited to teach <?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $t['subject_list']))) ?>. Please read this carefully. You can agree or decline; both are fine.</p>
        <div class="card section-card identity-card identity-card--vision"><h2 class="h3">Our philosophy</h2><p><?= e($philosophy) ?></p></div>
        <div class="card section-card">
            <h2 class="h3">Core principles</h2>
            <ol class="principles">
                <?php foreach ($principles as [$title, $meaning]): ?><li><strong><?= e($title) ?>.</strong> <?= e($meaning) ?></li><?php endforeach; ?>
            </ol>
        </div>
        <div class="card section-card">
            <h2 class="h3">What a team tutor does</h2>
            <ul><?php foreach ($duties as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul>
        </div>
        <form method="post" action="/team/agreement" class="form card">
            <?= $csrf->field() ?>
            <?php if ($error !== null): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <label class="field"><span class="field__label">To agree, type your full name: <strong><?= e($t['full_name']) ?></strong></span>
                <input type="text" name="signed_name" autocomplete="name"></label>
            <div class="form-actions decision__buttons">
                <button type="submit" name="decision" value="agree" class="btn btn--green">I agree and join the team</button>
                <button type="submit" name="decision" value="decline" class="btn btn--ghost">I do not agree</button>
            </div>
            <p class="note">If you decline, your account is closed and nothing else happens. Thank you for considering it.</p>
        </form>
    </div>
</section>
