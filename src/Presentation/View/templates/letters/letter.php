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
            <?php if (isset($data['reference'])): ?><p>Ref: <?= e($data['reference']) ?></p><?php endif; ?>
        </div>
    </header>

    <?php if ($doc['revoked_at'] !== null): ?>
        <p class="letter__void">This letter has been replaced and is no longer valid.</p>
    <?php endif; ?>

    <p class="letter__to">
        <?= e($data['full_name'] ?? '') ?><br>
        <?php if ($doc['type'] === 'partner_invite'): ?>
            <?= e($data['school'] ?? '') ?>, <?= e($data['district'] ?? '') ?>
        <?php else: ?>
            Form <?= e($data['form'] ?? '') ?><?= !empty($data['school']) ? ', ' . e($data['school']) : '' ?>
        <?php endif; ?>
    </p>

    <h1 class="letter__title"><?= e($doc['title']) ?></h1>

    <?php if (!in_array($doc['type'], ['pledge_certificate', 'term_report'], true)): ?><p>Dear <?= e($first) ?>,</p><?php endif; ?>

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
    <?php elseif ($doc['type'] === 'partner_invite'): ?>
        <p>
            I would like to invite you to become a <strong>school partner</strong> of <strong>SMART Circle</strong>, a free
            programme of recorded lessons in Mathematics, Physics, Chemistry, Biology and English grammar for up to
            24 committed Form 1–4 students.
        </p>
        <p>
            SMART Circle does not replace or compete with your teaching. We only explain again the topics your school has
            <em>already taught</em>, and we always show students why each topic matters and where it is used in real life in Malawi.
        </p>
        <p><strong>How you can help (a few minutes a month):</strong></p>
        <ol>
            <li>Tell us which topics in <strong><?= e($subjects) ?></strong> your school has taught, is teaching now, or teaches next.</li>
            <li>List the textbooks your students use, with the chapter and pages for each topic, so every lesson points them to their own book.</li>
            <li>See, without names, which topics your students find hardest, which may help your own lessons.</li>
        </ol>
        <p><strong>To start:</strong> open the SMART Circle website, choose <strong>Log in</strong> and then <strong>set up your account</strong>,
            and enter your phone number with the setup code the tutor gives you.</p>
        <p>Thank you for supporting your students.</p>
    <?php elseif ($doc['type'] === 'term_report'): ?>
        <p><strong><?= e($data['year'] ?? '') ?>, Term <?= e($data['term'] ?? '') ?></strong> · Form <?= e($data['form'] ?? '') ?>
            · <?= ($data['scale'] ?? '') === 'jce' ? 'JCE letters A–F' : 'MSCE points 1–9' ?></p>
        <table class="letter__table">
            <thead><tr><th>Subject</th><th>Term %</th><th>Grade</th></tr></thead>
            <tbody>
            <?php foreach ((array) ($data['subjects'] ?? []) as $sub): ?>
                <tr><td><?= e($sub['name'] ?? '') ?></td>
                    <td><?= $sub['percent'] === null ? '—' : e(rtrim(rtrim(number_format((float) $sub['percent'], 1), '0'), '.')) . '%' ?></td>
                    <td><?= !empty($sub['grade']) ? e($sub['grade']['grade']) . ' (' . e($sub['grade']['descriptor']) . ')' : 'No marked work' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (($data['overall'] ?? null) !== null): ?>
            <p><strong>Overall: <?= e(rtrim(rtrim(number_format((float) $data['overall'], 1), '0'), '.')) ?>%</strong><?= !empty($data['overall_grade']) ? ' · ' . e($data['overall_grade']['grade']) . ' (' . e($data['overall_grade']['descriptor']) . ')' : '' ?>
                <?= isset($data['graded'], $data['subject_count']) && $data['graded'] < $data['subject_count'] ? '<br><span class="letter__small">Overall from ' . e($data['graded']) . ' of ' . e($data['subject_count']) . ' subjects with marked work this term.</span>' : '' ?></p>
        <?php endif; ?>
        <?php if (!empty($data['comment'])): ?><p class="letter__comment"><strong>Tutor’s comment:</strong> <?= e($data['comment']) ?></p><?php endif; ?>
        <p class="letter__small">Term grades combine exercises, assignments, tests and exams
            (<?= e(implode(' / ', array_map('strval', (array) ($data['weights'] ?? [])))) ?>%). SMART Circle supports the student’s school; this report does not replace the school’s own report.</p>
    <?php elseif ($doc['type'] === 'pledge_certificate'): ?>
        <p>
            On <?= e($date) ?>, <strong><?= e($data['full_name'] ?? '') ?></strong> joined SMART Circle and made the following
            pledge. SMART Circle is free of money; its students pay for their place with discipline.
        </p>
        <ol class="letter__vows">
            <?php foreach ((array) ($data['vows'] ?? []) as $vow): ?><li><?= e($vow) ?></li><?php endforeach; ?>
        </ol>
        <p>Signed by the student, with the knowledge of <?= e($data['guardian_name'] ?? 'their parent or guardian') ?>.</p>
        <p class="letter__motto"><strong>Understand it. Apply it.</strong></p>
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

    <?php if (isset($data['guardian_name']) && !in_array($doc['type'], ['pledge_certificate', 'term_report'], true)): ?><p>Parent/guardian: <?= e($data['guardian_name']) ?></p><?php endif; ?>

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
