<?php
use SmartCircle\Support\Identity;
?>
<section class="hero hero--about">
    <div class="container narrow-wide">
        <p class="eyebrow">About SMART Circle</p>
        <h1>School that helps you understand the world, and your place in it</h1>
        <p class="lead">
            SMART stands for <strong>Student Mentorship, Academic Readiness and Technology</strong>: free support for
            Form 1–4 students in Mathematics, Physics, Chemistry, Biology and English grammar.
        </p>
    </div>
</section>

<section class="section">
    <div class="container identity-grid">
        <div class="card identity-card identity-card--vision">
            <h2>Our vision</h2>
            <p><?= e(Identity::VISION) ?></p>
        </div>
        <div class="card identity-card identity-card--mission">
            <h2>Our mission</h2>
            <p><?= e(Identity::MISSION) ?></p>
        </div>
    </div>
</section>

<section class="section section--mist">
    <div class="container">
        <h2>Our values</h2>
        <ul class="values">
            <?php foreach (Identity::VALUES as $key => [$value, $meaning]): ?>
                <li class="value value--<?= e($key) ?>"><strong><?= e($value) ?></strong><span><?= e($meaning) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="section">
    <div class="container story">
        <?php $photo = isset($siteAssets) ? $siteAssets()['photo'] : null; ?>
        <?php if ($photo !== null): ?><img class="story__photo story__photo--img" src="/site/photo?v=<?= e($photo) ?>" alt="Blessings Emulyn, founder and tutor of SMART Circle" width="170" height="170"><?php else: ?><div class="story__photo" aria-hidden="true"><span>BE</span></div><?php endif; ?>
        <div class="story__text prose">
            <p class="eyebrow">Why I started SMART Circle</p>
            <h2>My story</h2>
            <p>
                I believe school helps us understand the forces, standards and procedures others use to run the world,
                and to see what is already near us, or within us, that can help. School can free us from mediocrity,
                and make us independent or interdependent without being used as anyone’s tool.
            </p>
            <p>
                Above all, I believe school is not only there to get someone employed. It is there for personal development,
                which is useful in many areas of life, not only in working for others. But school is not anyone’s whole destiny.
            </p>
            <p>
                I started long before this website. In 2018, while I was writing my MANEB examinations at
                <strong>Ntcheu District Secondary School</strong>, I was already encouraging my sisters and friends.
                During my university holidays from 2019 to 2022 I helped Form 4 students at a community day secondary school in Ntcheu,
                and since May 2026 I have been helping Form 3 and 4 students. Every time it has been free,
                from my own resources, and without favours of any kind. This is simply what I love doing.
            </p>
            <p>
                So I have put my heart into helping young people in my country do well in school, so that they can live a life of
                their choice, a life they love. Even those who do not reach college should leave with a practical understanding
                they can use every day.
            </p>
            <p>
                I want to see young people who know what they are doing; who can apply every concept they learn; who never
                entertain mediocrity; who see the big picture; who are developed in their minds and develop others; who believe
                they can do anything in this world; and who are honest, and brave enough to be themselves.
                In short: happy young people who are of real benefit to others and to the world.
            </p>
            <p class="story__signature"><strong>Blessings Emulyn</strong><br>
                <span class="muted">Founder and tutor · BSc (Hons) Metallurgy and Materials Engineering,
                Malawi University of Science and Technology (MUST)</span></p>
        </div>
    </div>
</section>

<section class="section section--mist">
    <div class="container">
        <h2>Our goals</h2>
        <ol class="goals">
            <?php foreach (Identity::GOALS as $goal): ?><li><?= e($goal) ?></li><?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section">
    <div class="container prose">
        <h2>How SMART Circle works with schools</h2>
        <ul>
            <li>Students only get lessons, exercises and exams on topics <strong>their own school has already taught</strong>.</li>
            <li>Each request says exactly where the student gets stuck, and every lesson shows where the idea is used in real life.</li>
            <li>Partner teachers tell us what they have covered and which textbooks they use, and see (without names) where their students need help.</li>
        </ul>
        <p><a class="btn btn--ghost" href="/safeguarding">How we keep students safe</a></p>
    </div>
</section>
