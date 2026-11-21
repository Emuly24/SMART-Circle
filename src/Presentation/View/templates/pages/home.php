<?php
/**
 * @var list<array{key: string, name: string, blurb: string}> $subjects
 * @var \SmartCircle\Application\Admissions\SeatChart|null $chart
 * @var bool $isOpen
 * @var callable $asset
 */
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__text">
            <p class="eyebrow">Free · Forms 1–4 · Malawi</p>
            <h1>Understand it. <span class="text-accent">Apply it.</span></h1>
            <p class="lead">
                Free extra help in Mathematics, Physics, Chemistry, Biology and English grammar
                for serious Form 1–4 students. You tell us exactly which topic is giving you trouble,
                and the lesson is made for you, with real-life examples from Malawi.
            </p>
            <div class="hero__actions">
                <?php if ($isOpen): ?>
                    <a class="btn btn--green" href="/apply">Apply now. It's free</a>
                    <a class="btn btn--ghost" href="/about">How it works</a>
                <?php else: ?>
                    <a class="btn" href="/about">How it works</a>
                    <span class="hero__note">Applications open soon</span>
                <?php endif; ?>
            </div>
            <p class="hero__links"><a href="/apply/status">Check my application</a> · <a href="/welcome">Admitted? Set up my account</a></p>
        </div>
        <img class="hero__mark" src="<?= e($asset('brand/logo-mark.svg')) ?>" alt="" width="260" height="260">
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>How SMART Circle works</h2>
        <ol class="steps">
            <li class="step">
                <span class="step__num">1</span>
                <h3>Apply</h3>
                <p>Tell us your form, school and subjects, and take a short entry quiz.</p>
            </li>
            <li class="step">
                <span class="step__num">2</span>
                <h3>Request a topic</h3>
                <p>Pick a topic your class has covered and say exactly where you get lost.</p>
            </li>
            <li class="step">
                <span class="step__num">3</span>
                <h3>Watch the lesson</h3>
                <p>A clear lesson recorded for your request. Pause, rewind, or save it to watch offline.</p>
            </li>
            <li class="step">
                <span class="step__num">4</span>
                <h3>Check and discuss</h3>
                <p>Quick questions, exams and assignments show you've understood. Discuss with classmates.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section section--mist">
    <div class="container">
        <h2>Subjects</h2>
        <div class="subject-grid">
            <?php foreach ($subjects as $subject): ?>
                <article class="subject-card">
                    <span class="badge badge--<?= e($subject['key']) ?>"><?= e($subject['name']) ?></span>
                    <p><?= e($subject['blurb']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="note">
            Forms 1–2 take all five. In Forms 3–4, science students (Physics and Chemistry) take all five;
            humanities students take Mathematics, Biology and English grammar.
        </p>
    </div>
</section>

<section class="section">
    <div class="container two-col">
        <div>
            <h2>A small, serious class</h2>
            <p>
                24 places: six per form, three girls and three boys, from different schools.
                It's free, and it's for students who take their schoolwork seriously.
            </p>
            <?= $view->partial('seat_chart', ['chart' => $chart]) ?>
            <p>
                Every lesson answers two questions: <strong>why does this matter?</strong> and
                <strong>where will I use it in real life?</strong>
            </p>
        </div>
        <div>
            <h2>Working with your school</h2>
            <p>
                SMART Circle supports schools and teachers, never replaces them. Lessons follow the
                national syllabus and your school's pace, and partner teachers help keep them accurate.
            </p>
            <p>
                Not admitted? Free handouts from every lesson will be available to everyone in the library.
            </p>
        </div>
    </div>
</section>
