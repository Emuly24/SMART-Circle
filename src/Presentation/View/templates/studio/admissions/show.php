<?php
/**
 * @var array<string, mixed> $app
 * @var list<array<string, mixed>> $answers
 * @var list<array<string, mixed>> $letters
 * @var bool $hasSeat
 * @var array<string, string> $subjects
 * @var array<string, string> $tracks
 * @var array<string, string> $devices
 * @var array<string, string> $dataAccess
 * @var string|null $setupCode
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;

$names = static fn (string $csv): string => Subjects::names(array_values(array_filter(explode(',', $csv))));
$statusLabels = ['submitted' => 'To review', 'shortlisted' => 'Shortlisted', 'admitted' => 'Admitted', 'waitlisted' => 'Waiting list', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'];
$first = explode(' ', (string) $app['full_name'])[0];
$waPhone = ltrim((string) $app['phone'], '+');
$waText = "Hello $first, congratulations on your admission to SMART Circle! To set up your account, open the SMART Circle website, choose \"Set up my account\" and enter your phone number and this code: " . ($setupCode ?? '') . '. The code works for 14 days.';
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/admissions">Admissions</a></p>
        <h1><?= e($app['full_name']) ?></h1>
        <p class="lead">
            <span class="status status--<?= e($app['status']) ?>"><?= e($statusLabels[$app['status']] ?? $app['status']) ?></span>
            <span class="muted">· <?= e($app['reference']) ?> · applied <?= e(date('j M Y', strtotime((string) $app['created_at']))) ?><?= $app['source'] === 'paper' ? ' · paper' : '' ?></span>
        </p>

        <?php if ($setupCode !== null): ?>
            <div class="card setup-code">
                <h2>Setup code for <?= e($first) ?></h2>
                <p class="reference"><?= e(implode(' ', str_split($setupCode, 4))) ?></p>
                <p>Give this to the student (it works for 14 days). They open <strong>Set up my account</strong>, enter their phone number and this code, then choose a password.</p>
                <div class="setup-code__actions">
                    <a class="btn btn--green" href="https://wa.me/<?= e($waPhone) ?>?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener">Send on WhatsApp</a>
                    <form method="post" action="/studio/admissions/<?= e($app['id']) ?>/code/hide"><?= $csrf->field() ?><button class="btn btn--ghost btn--small" type="submit">I have sent it, hide the code</button></form>
                </div>
            </div>
        <?php endif; ?>

        <div class="detail-grid">
            <div class="card">
                <h2>Student</h2>
                <dl class="facts">
                    <dt>Form</dt><dd>Form <?= e($app['form']) ?> · <?= e($tracks[$app['track']] ?? $app['track']) ?></dd>
                    <dt>Gender</dt><dd><?= $app['gender'] === 'female' ? 'Girl' : 'Boy' ?></dd>
                    <dt>School</dt><dd><?= e($app['school_name'] ?? '') ?>, <?= e($app['district'] ?? '') ?></dd>
                    <dt>Phone</dt><dd><?= e(PhoneNumber::display((string) $app['phone'])) ?></dd>
                    <?php if ($app['email']): ?><dt>Email</dt><dd><?= e($app['email']) ?></dd><?php endif; ?>
                    <dt>Takes</dt><dd><?= e($names((string) $app['subjects_taken'])) ?></dd>
                    <dt>Wants help in</dt><dd><?= e($names((string) $app['help_subjects'])) ?></dd>
                    <dt>Device / data</dt><dd><?= e($devices[$app['device']] ?? $app['device']) ?> · <?= e($dataAccess[$app['data_access']] ?? $app['data_access']) ?></dd>
                </dl>
            </div>
            <div class="card">
                <h2>Guardian</h2>
                <dl class="facts">
                    <dt>Name</dt><dd><?= e($app['guardian_name']) ?> (<?= e($app['guardian_relationship']) ?>)</dd>
                    <dt>Phone</dt><dd><?= e(PhoneNumber::display((string) $app['guardian_phone'])) ?></dd>
                    <dt>Consent</dt><dd><?= $app['guardian_consent_at'] ? 'Given ' . e(date('j M Y', strtotime((string) $app['guardian_consent_at']))) : 'Missing' ?></dd>
                </dl>
                <h2>School report</h2>
                <?php if ($app['report_path']): ?>
                    <p><a class="btn btn--small btn--ghost" href="/studio/admissions/<?= e($app['id']) ?>/report" target="_blank" rel="noopener">Open the report</a></p>
                <?php else: ?>
                    <p class="muted">No report uploaded.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php $survey = \SmartCircle\Application\Admissions\Questionnaire::summary(json_decode((string) ($app['answers'] ?? ''), true) ?: []); ?>
        <?php if ($survey['rows'] !== []): ?>
            <div class="card">
                <h2>Goals and habits</h2>
                <p class="signal-summary">
                    <span class="signal signal--growth"><?= e($survey['counts']['growth']) ?> growth-minded</span>
                    <span class="signal signal--neutral"><?= e($survey['counts']['neutral']) ?> neutral</span>
                    <span class="signal signal--concern"><?= e($survey['counts']['concern']) ?> worth a conversation</span>
                </p>
                <p class="note">Signals help you choose follow-up questions. They never decide an application on their own.</p>
                <dl class="survey">
                    <?php foreach ($survey['rows'] as $row): ?>
                        <dt><?= e($row['label']) ?></dt>
                        <dd class="survey__answer survey__answer--<?= e($row['signal']) ?>"><?= nl2br(e($row['answer'])) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Why they want to join</h2>
            <p><?= nl2br(e($app['motivation'])) ?></p>
            <?php if ($app['struggling_topics']): ?>
                <h2>Topics they struggle with</h2>
                <p><?= nl2br(e($app['struggling_topics'])) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Entry quiz <?= $app['quiz_total'] !== null ? '· ' . e($app['quiz_score']) . ' / ' . e($app['quiz_total']) : '' ?></h2>
            <?php if ($answers === []): ?>
                <p class="muted">Not taken yet.</p>
            <?php else: ?>
                <ol class="quiz-review">
                    <?php foreach ($answers as $a): ?>
                        <li class="<?= $a['is_correct'] ? 'is-right' : 'is-wrong' ?>">
                            <span class="badge badge--<?= e($a['subject']) ?>"><?= e($subjects[$a['subject']] ?? '') ?></span>
                            <?= e($a['question']) ?> <strong><?= $a['is_correct'] ? '✓' : '✗ (answered ' . e(strtoupper((string) ($a['chosen'] ?? '–'))) . ', correct ' . e(strtoupper((string) $a['correct'])) . ')' ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <?php if ($app['status'] !== 'admitted'): ?>
            <form method="post" action="/studio/admissions/<?= e($app['id']) ?>/decision" class="card form decision">
                <?= $csrf->field() ?>
                <h2>Decision</h2>
                <?php if (!$hasSeat): ?>
                    <div class="alert alert--info">All Form <?= e($app['form']) ?> <?= $app['gender'] === 'female' ? 'girls' : 'boys' ?>' seats are taken. You can shortlist, waitlist or decline.</div>
                <?php endif; ?>
                <label class="field">
                    <span class="field__label">Private note <span class="muted">(only you see this)</span></span>
                    <textarea name="note" rows="2"><?= e($app['tutor_note'] ?? '') ?></textarea>
                </label>
                <div class="decision__buttons">
                    <button type="submit" name="decision" value="admit" class="btn btn--green" <?= $hasSeat ? '' : 'disabled' ?>>Admit</button>
                    <button type="submit" name="decision" value="shortlist" class="btn btn--ghost">Shortlist</button>
                    <button type="submit" name="decision" value="waitlist" class="btn btn--ghost">Waiting list</button>
                    <button type="submit" name="decision" value="decline" class="btn btn--danger">Decline</button>
                </div>
            </form>
        <?php else: ?>
            <div class="card">
                <h2>Student account</h2>
                <p>The account uses <?= e(PhoneNumber::display((string) $app['phone'])) ?>. If the student lost their code, make a new one.</p>
                <form method="post" action="/studio/admissions/<?= e($app['id']) ?>/code" class="js-confirm" data-confirm="Make a new setup code? The old one will stop working.">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small btn--ghost">Make a new setup code</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($letters !== []): ?>
            <div class="card">
                <h2>Letters</h2>
                <ul class="letter-list">
                    <?php foreach ($letters as $l): ?>
                        <li>
                            <a href="/letters/<?= e($l['code']) ?>" target="_blank" rel="noopener"><?= e($l['title']) ?></a>
                            <span class="muted">· <?= e($l['code']) ?> · <?= e(date('j M Y', strtotime((string) $l['issued_at']))) ?><?= $l['revoked_at'] ? ' · replaced' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
