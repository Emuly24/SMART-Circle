<?php
/**
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var \SmartCircle\Application\Admissions\SeatChart|null $chart
 * @var array<string, string> $subjects
 * @var list<string> $districts
 * @var array<string, string> $devices
 * @var array<string, string> $dataAccess
 * @var list<string> $relationships
 * @var bool $isTutor
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var callable $asset
 */
$v = static fn (string $k): string => is_scalar($old[$k] ?? null) ? (string) $old[$k] : '';
$list = static fn (string $k): array => is_array($old[$k] ?? null) ? $old[$k] : [];
$err = static fn (string $k): string => isset($errors[$k]) ? '<span class="field__error" role="alert">' . e($errors[$k]) . '</span>' : '';
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="eyebrow">Free · Forms 1–4</p>
        <h1>Apply to SMART Circle</h1>
        <p class="lead">It takes about 10 minutes, then a short entry quiz (20 minutes). Have your parent or guardian with you.</p>
        <?php if ($isTutor): ?>
            <div class="alert alert--info">You are logged in as the tutor: this application will be saved as a <strong>paper application</strong>.</div>
        <?php endif; ?>

        <details class="card seats-card">
            <summary>Seats available (6 per form: 3 girls and 3 boys)</summary>
            <?= $view->partial('seat_chart', ['chart' => $chart]) ?>
        </details>

        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert">
                <?= isset($errors['form']) ? e($errors['form']) : 'Please fix the highlighted answers below.' ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/apply" enctype="multipart/form-data" class="form apply-form" id="apply-form" novalidate>
            <?= $csrf->field() ?>
            <!-- Leave empty: helps stop automated spam -->
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <fieldset class="card">
                <legend>About you</legend>
                <label class="field">
                    <span class="field__label">Full name</span>
                    <input type="text" name="full_name" value="<?= e($v('full_name')) ?>" autocomplete="name" required>
                    <?= $err('full_name') ?>
                </label>
                <div class="field-row">
                    <div class="field">
                        <span class="field__label">I am a</span>
                        <div class="choice-row">
                            <label class="choice"><input type="radio" name="gender" value="female" <?= $v('gender') === 'female' ? 'checked' : '' ?>> Girl</label>
                            <label class="choice"><input type="radio" name="gender" value="male" <?= $v('gender') === 'male' ? 'checked' : '' ?>> Boy</label>
                        </div>
                        <?= $err('gender') ?>
                    </div>
                    <label class="field">
                        <span class="field__label">Form</span>
                        <select name="form" id="form-select" required>
                            <option value="">Choose…</option>
                            <?php for ($f = 1; $f <= 4; $f++): ?>
                                <option value="<?= $f ?>" <?= $v('form') === (string) $f ? 'selected' : '' ?>>Form <?= $f ?></option>
                            <?php endfor; ?>
                        </select>
                        <?= $err('form') ?>
                    </label>
                </div>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Phone number (you will log in with it)</span>
                        <input type="tel" name="phone" value="<?= e($v('phone')) ?>" inputmode="tel" autocomplete="tel" placeholder="0999 123 456" required>
                        <?= $err('phone') ?>
                    </label>
                    <label class="field">
                        <span class="field__label">Email <span class="muted">(optional)</span></span>
                        <input type="email" name="email" value="<?= e($v('email')) ?>" autocomplete="email">
                        <?= $err('email') ?>
                    </label>
                </div>
            </fieldset>

            <fieldset class="card">
                <legend>Your school</legend>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">School name</span>
                        <input type="text" name="school_name" value="<?= e($v('school_name')) ?>" required>
                        <?= $err('school_name') ?>
                    </label>
                    <label class="field">
                        <span class="field__label">District</span>
                        <select name="district" required>
                            <option value="">Choose…</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= e($d) ?>" <?= $v('district') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('district') ?>
                    </label>
                </div>
                <div class="field">
                    <span class="field__label">Which of these subjects do you take at school?</span>
                    <div class="choice-grid">
                        <?php foreach ($subjects as $key => $name): ?>
                            <label class="choice"><input type="checkbox" name="subjects_taken[]" value="<?= e($key) ?>" <?= in_array($key, $list('subjects_taken'), true) ? 'checked' : '' ?>> <?= e($name) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="note" id="track-note">Forms 3–4: students who take Physics and Chemistry can get help in all five subjects; others in Mathematics, Biology and English grammar.</p>
                    <?= $err('subjects_taken') ?>
                </div>
                <div class="field">
                    <span class="field__label">Which subjects do you want help with?</span>
                    <div class="choice-grid">
                        <?php foreach ($subjects as $key => $name): ?>
                            <label class="choice" data-help="<?= e($key) ?>"><input type="checkbox" name="help_subjects[]" value="<?= e($key) ?>" <?= in_array($key, $list('help_subjects'), true) ? 'checked' : '' ?>> <?= e($name) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('help_subjects') ?>
                </div>
                <label class="field">
                    <span class="field__label">Which topics give you the most trouble? <span class="muted">(optional)</span></span>
                    <textarea name="struggling_topics" rows="3" maxlength="1000" placeholder="e.g. Moles in Chemistry, factorising in Maths, reported speech"><?= e($v('struggling_topics')) ?></textarea>
                </label>
                <label class="field">
                    <span class="field__label">Latest school report <span class="muted">(optional: photo or PDF, up to 3 MB)</span></span>
                    <input type="file" name="report" id="report" accept="image/jpeg,image/png,application/pdf">
                    <span class="note" id="report-note">Photos are made smaller on your phone before uploading, to save data.</span>
                    <?= $err('report') ?>
                </label>
            </fieldset>

            <fieldset class="card">
                <legend>Why you want to join</legend>
                <label class="field">
                    <span class="field__label">Why do you want to join SMART Circle, and how will you use it?</span>
                    <textarea name="motivation" rows="5" maxlength="1500" required><?= e($v('motivation')) ?></textarea>
                    <?= $err('motivation') ?>
                </label>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">How will you watch lessons?</span>
                        <select name="device" required>
                            <option value="">Choose…</option>
                            <?php foreach ($devices as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $v('device') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('device') ?>
                    </label>
                    <label class="field">
                        <span class="field__label">How often do you have internet data?</span>
                        <select name="data_access" required>
                            <option value="">Choose…</option>
                            <?php foreach ($dataAccess as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $v('data_access') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('data_access') ?>
                    </label>
                </div>
            </fieldset>

            <fieldset class="card">
                <legend>Parent or guardian</legend>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label">Full name</span>
                        <input type="text" name="guardian_name" value="<?= e($v('guardian_name')) ?>" required>
                        <?= $err('guardian_name') ?>
                    </label>
                    <label class="field">
                        <span class="field__label">Phone number</span>
                        <input type="tel" name="guardian_phone" value="<?= e($v('guardian_phone')) ?>" inputmode="tel" placeholder="0888 123 456" required>
                        <?= $err('guardian_phone') ?>
                    </label>
                </div>
                <label class="field">
                    <span class="field__label">Relationship to you</span>
                    <select name="guardian_relationship" required>
                        <option value="">Choose…</option>
                        <?php foreach ($relationships as $r): ?>
                            <option value="<?= e($r) ?>" <?= $v('guardian_relationship') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('guardian_relationship') ?>
                </label>
                <label class="choice choice--block">
                    <input type="checkbox" name="guardian_consent" value="1" <?= $v('guardian_consent') === '1' ? 'checked' : '' ?>>
                    My parent or guardian has read this form and agrees that I may join SMART Circle, a free online programme
                    where I watch recorded lessons and take part in class discussions that the whole class can see.
                </label>
                <?= $err('guardian_consent') ?>
                <label class="choice choice--block">
                    <input type="checkbox" name="truthful" value="1" <?= $v('truthful') === '1' ? 'checked' : '' ?>>
                    The answers on this form are true.
                </label>
                <?= $err('truthful') ?>
            </fieldset>

            <button type="submit" class="btn btn--block btn--green">Submit and go to the entry quiz</button>
            <p class="note center">Already applied? <a href="/apply/status">Check my application</a></p>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/apply.js')) ?>" defer></script>
