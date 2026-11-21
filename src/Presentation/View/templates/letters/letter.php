<?php
/**
 * Branded letter: admission, waiting list or decline.
 * @var array<string, mixed> $doc
 * @var array<string, mixed> $data
 * @var string $subjects
 * @var string $verifyUrl
 * @var callable $asset
 */
$first = explode(' ', (string) ($data['full_name'] ?? ''))[0];
$date = date('j F Y', strtotime((string) $doc['issued_at']));
?>
<article class="letter">
    <header class="letter__head">
        <img src="<?= e($asset('brand/logo-horizontal.svg')) ?>" alt="SMART Circle" class="letter__logo">
        <div class="letter__meta">
            <p><?= e($date) ?></p>
            <p>Ref: <?= e($data['reference'] ?? '') ?></p>
        </div>
    </header>

    <?php if ($doc['revoked_at'] !== null): ?>
        <p class="letter__void">This letter has been replaced and is no longer valid.</p>
    <?php endif; ?>

    <p class="letter__to">
        <?= e($data['full_name'] ?? '') ?><br>
        Form <?= e($data['form'] ?? '') ?>, <?= e($data['school'] ?? '') ?>
    </p>

    <h1 class="letter__title"><?= e($doc['title']) ?></h1>

    <p>Dear <?= e($first) ?>,</p>

    <?php if ($doc['type'] === 'admission_letter'): ?>
        <p>
            Congratulations! I am pleased to offer you a place in <strong>SMART Circle</strong>, a free programme
            of recorded lessons for Form 1–4 students who take their studies seriously.
        </p>
        <p>You will receive support in: <strong><?= e($subjects) ?></strong>.</p>
        <p><strong>Your next steps:</strong></p>
        <ol>
            <li>Get your <strong>setup code</strong> from the tutor (by WhatsApp or in person).</li>
            <li>Open the SMART Circle website, choose <strong>Set up my account</strong>, and enter your phone number and the code.</li>
            <li>Choose a password, read the class agreement, and start requesting topics your class has covered.</li>
        </ol>
        <p>
            Every lesson will show you not only <em>how</em> but also <em>why it matters</em> and <em>where it is used in real life</em>.
            I look forward to learning with you.
        </p>
    <?php elseif ($doc['type'] === 'waitlist_letter'): ?>
        <p>
            Thank you for applying to <strong>SMART Circle</strong>. Your application was strong, but all the seats for
            your form are currently filled. You have been placed on the <strong>waiting list</strong>.
        </p>
        <p>If a seat becomes available, you will be contacted using the phone number on your application.</p>
        <p>Meanwhile, the free lesson handouts in the SMART Circle library are open to everyone.</p>
    <?php else: ?>
        <p>
            Thank you for applying to <strong>SMART Circle</strong>. We had more applications than places, and this time
            we are not able to offer you a seat.
        </p>
        <p>
            Please don't be discouraged. The free lesson handouts in the SMART Circle library are open to everyone, and you
            are welcome to apply again in the next intake.
        </p>
    <?php endif; ?>

    <p>Parent/guardian: <?= e($data['guardian_name'] ?? '') ?></p>

    <div class="letter__sign">
        <p>Yours sincerely,</p>
        <div class="letter__signature" aria-hidden="true"></div>
        <p><strong>The Tutor</strong><br>SMART Circle</p>
    </div>

    <footer class="letter__foot">
        <p>Verify this letter: <strong><?= e($verifyUrl) ?></strong> · Code <strong><?= e($doc['code']) ?></strong></p>
        <p>SMART Circle · Understand it. Apply it. · smartcircle@zohomail.com</p>
    </footer>
</article>
