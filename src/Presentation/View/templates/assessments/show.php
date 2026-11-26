<?php
/**
 * @var array<string, mixed> $a
 * @var string $state
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var array<string, string> $states
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/assessments">Exams &amp; assignments</a></p>
        <p><span class="badge badge--<?= e($a['subject']) ?>"><?= e($names[$a['subject']] ?? '') ?></span> <span class="muted"><?= e($kinds[$a['kind']] ?? '') ?></span></p>
        <h1><?= e($a['title']) ?></h1>
        <div class="card">
            <p class="access-card__state"><?= e($states[$state] ?? '') ?></p>
            <dl class="facts">
                <?php if ($a['duration_min']): ?><dt>Time</dt><dd><?= e($a['duration_min']) ?> minutes, starting when you press Start</dd><?php endif; ?>
                <?php if ($a['opens_at']): ?><dt>Opens</dt><dd><?= e(date('D j M Y, H:i', (int) strtotime((string) $a['opens_at']))) ?></dd><?php endif; ?>
                <?php if ($a['closes_at']): ?><dt>Closes</dt><dd><?= e(date('D j M Y, H:i', (int) strtotime((string) $a['closes_at']))) ?></dd><?php endif; ?>
                <dt>Attempts</dt><dd>One</dd>
            </dl>
            <?php if ($a['instructions']): ?><p class="assess-instructions"><?= nl2br(e($a['instructions'])) ?></p><?php endif; ?>
            <?php if ($state === 'open'): ?>
                <form method="post" action="/assessments/<?= e($a['id']) ?>/start" class="js-confirm" data-confirm="<?= $a['duration_min'] ? 'The ' . e($a['duration_min']) . '-minute timer starts now and cannot be paused. Start?' : 'Start now? You can save and come back before it closes.' ?>">
                    <?= $csrf->field() ?><button type="submit" class="btn btn--green">Start</button>
                </form>
            <?php elseif ($state === 'in_progress'): ?>
                <p><a class="btn" href="/assessments/<?= e($a['id']) ?>/take">Continue</a></p>
            <?php elseif ($state === 'results'): ?>
                <p><a class="btn" href="/assessments/<?= e($a['id']) ?>/result">See your results</a></p>
            <?php elseif ($state === 'locked'): ?>
                <p>SMART Circle only tests you on what your own school has already taught. This opens when your school reports it has taught every topic in it.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
