<?php /** @var array<string, mixed> $app */ ?>
<section class="section">
    <div class="container narrow-wide">
        <div class="card center done-card">
            <p class="done-card__icon" aria-hidden="true">✓</p>
            <h1>Application received</h1>
            <p class="lead">Thank you, <?= e(explode(' ', (string) $app['full_name'])[0]) ?>. Keep your reference number safe:</p>
            <p class="reference"><?= e($app['reference']) ?></p>
            <?php if ($app['quiz_finished_at'] !== null): ?>
                <p>Entry quiz completed.</p>
            <?php else: ?>
                <p><a class="btn" href="/apply/quiz">Take the entry quiz now</a></p>
            <?php endif; ?>
            <p>
                The tutor reviews every application. Check your result any time on
                <a href="/apply/status">Check my application</a> with your reference and phone number.
            </p>
        </div>
    </div>
</section>
