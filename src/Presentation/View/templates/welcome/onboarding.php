<?php
/**
 * Sign-up details and the SMART Circle Pledge.
 * @var array<string, mixed>|null $profile
 * @var array<string, mixed>|null $user
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var array<string, string> $modes
 * @var array<string, string> $travel
 * @var array<string, string> $homeStudy
 * @var array<string, array{0: string, 1: string}> $vows
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error" role="alert">' . e($errors[$f]) . '</span>' : '';
$v = static fn (string $k): string => is_scalar($old[$k] ?? null) ? (string) $old[$k] : '';
$ticked = is_array($old['vows'] ?? null) ? $old['vows'] : [];
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="eyebrow">One last step</p>
        <h1>Welcome to SMART Circle</h1>
        <p class="lead">SMART Circle is free of money. You pay for your place with something more valuable: your discipline.</p>
        <?php if ($errors !== []): ?><div class="alert alert--error" role="alert">Please complete the highlighted parts below.</div><?php endif; ?>

        <form method="post" action="/welcome/onboarding" class="form onboarding">
            <?= $csrf->field() ?>
            <fieldset class="card">
                <legend>How you attend school</legend>
                <p class="muted"><?= e($profile['school_name'] ?? 'Your school') ?><?= !empty($profile['district']) ? ', ' . e($profile['district']) : '' ?></p>
                <div class="field">
                    <span class="field__label">I am a…</span>
                    <div class="choice-grid choice-grid--wide">
                        <?php foreach ($modes as $key => $label): ?>
                            <label class="choice"><input type="radio" name="study_mode" value="<?= e($key) ?>" class="js-study-mode" <?= $v('study_mode') === $key ? 'checked' : '' ?>> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('study_mode') ?>
                </div>
                <label class="field"><span class="field__label">Where is your school? <span class="muted">(area, trading centre or Traditional Authority)</span></span>
                    <input type="text" name="school_area" value="<?= e($v('school_area')) ?>" maxlength="120" placeholder="e.g. Kanengo, Area 25"><?= $err('school_area') ?></label>
                <label class="field js-travel"><span class="field__label">How long does it take you to reach school?</span>
                    <select name="travel_time"><option value="">Choose…</option>
                        <?php foreach ($travel as $key => $label): ?><option value="<?= e($key) ?>" <?= $v('travel_time') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select><?= $err('travel_time') ?></label>
                <label class="field"><span class="field__label">When do you usually have time to study and watch lessons?</span>
                    <select name="home_study"><option value="">Choose…</option>
                        <?php foreach ($homeStudy as $key => $label): ?><option value="<?= e($key) ?>" <?= $v('home_study') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select><?= $err('home_study') ?></label>
            </fieldset>

            <fieldset class="card pledge">
                <legend>The SMART Circle Pledge</legend>
                <p class="muted">Read each promise and tick it when you mean it.</p>
                <ol class="pledge__vows">
                    <?php foreach ($vows as $key => [$vow, $why]): ?>
                        <li>
                            <label class="choice choice--block pledge__vow">
                                <input type="checkbox" name="vows[]" value="<?= e($key) ?>" <?= in_array($key, $ticked, true) ? 'checked' : '' ?>>
                                <span><strong><?= e($vow) ?></strong> <span class="muted"><?= e($why) ?></span></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?= $err('vows') ?>
                <label class="field"><span class="field__label">Sign by typing your full name: <?= e($user['full_name'] ?? '') ?></span>
                    <input type="text" name="signed_name" value="<?= e($v('signed_name')) ?>" autocomplete="off" class="pledge__signature"><?= $err('signed_name') ?></label>
                <p class="note">The tutor will also tell your parent or guardian<?= !empty($profile['guardian_name']) ? ' (' . e($profile['guardian_name']) . ')' : '' ?> about SMART Circle and your pledge, in the language they understand.</p>
            </fieldset>
            <button type="submit" class="btn btn--green btn--block">Sign my pledge and start learning</button>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/onboarding.js')) ?>" defer></script>
