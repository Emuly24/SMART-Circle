<?php
/**
 * @var array<string, mixed>|null $profile
 * @var string $error
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section">
    <div class="container narrow-wide">
        <p class="eyebrow">One last step</p>
        <h1>Welcome to SMART Circle</h1>
        <p class="lead">You are part of a small class of 24 students from different schools. Here is how we learn together.</p>

        <div class="card agreement">
            <h2>Our class agreement</h2>
            <ol>
                <li><strong>I take part.</strong> I watch the lessons I asked for, answer the questions inside them, and do my exams and assignments honestly.</li>
                <li><strong>I ask when I'm stuck.</strong> I request topics my school has covered and say exactly where I get lost.</li>
                <li><strong>I respect everyone.</strong> I write kindly in discussions. Everything I post can be seen by the whole class and the tutor.</li>
                <li><strong>I keep my account to myself.</strong> I don't share my password or give my place to anyone else.</li>
                <li><strong>I stay safe.</strong> I don't share my home address or private details, and I tell the tutor or my guardian if anything worries me.</li>
                <li><strong>My school comes first.</strong> SMART Circle supports my teachers; it never replaces them.</li>
                <li><strong>My place depends on effort.</strong> If I stop taking part for a long time, my seat may go to a student on the waiting list.</li>
            </ol>
        </div>

        <?php if ($error !== ''): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/welcome/onboarding" class="card form">
            <?= $csrf->field() ?>
            <label class="choice choice--block">
                <input type="checkbox" name="agreement" value="1"> I have read the class agreement and I will follow it.
            </label>
            <label class="choice choice--block">
                <input type="checkbox" name="guardian" value="1">
                My parent or guardian<?= !empty($profile['guardian_name']) ? ' (' . e($profile['guardian_name']) . ')' : '' ?> knows I am joining SMART Circle.
            </label>
            <button type="submit" class="btn btn--green btn--block">Start learning</button>
        </form>
    </div>
</section>
