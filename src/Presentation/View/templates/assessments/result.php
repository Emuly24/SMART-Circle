<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $names
 */
$a = $r['assessment'];
$s = $r['submission'];
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
$pct = (float) $s['max_score'] > 0 ? (int) round(100 * (float) $s['score'] / (float) $s['max_score']) : 0;
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/assessments">Exams &amp; assignments</a></p>
        <h1><?= e($a['title']) ?></h1>
        <div class="card result-card">
            <p class="result-card__score"><strong><?= e($fmt($s['score'])) ?></strong> / <?= e($fmt($s['max_score'])) ?> <span class="muted">(<?= e($pct) ?>%)</span></p>
            <?php if ($s['feedback']): ?><p class="result-card__feedback"><?= nl2br(e($s['feedback'])) ?></p><?php endif; ?>
        </div>
        <?php foreach ($r['questions'] as $i => $q):
            $ans = $r['answers'][(int) $q['id']] ?? [];
            $got = $ans['manual_score'] ?? $ans['auto_score'] ?? 0;
            $answer = (string) ($ans['answer'] ?? '');
            $full = (float) $got >= (float) $q['max_marks'];
            $shown = $q['type'] === 'mcq' && $answer !== '' ? (string) ($q['data']['options'][(int) $answer] ?? '') : $answer; ?>
            <div class="card result-q result-q--<?= $full ? 'right' : ((float) $got > 0 ? 'part' : 'wrong') ?>">
                <p class="question-row__meta"><strong>Question <?= $i + 1 ?></strong> <span class="chip"><?= e($fmt($got)) ?> / <?= e($fmt($q['max_marks'])) ?></span></p>
                <p><?= nl2br(e($q['prompt'])) ?></p>
                <p><span class="muted">Your answer:</span> <?= $shown === '' ? '<em class="muted">No answer</em>' : nl2br(e($shown)) ?></p>
                <?php if (!$full && $q['type'] === 'mcq'): ?><p><span class="muted">Correct answer:</span> <?= e($q['data']['options'][(int) ($q['data']['correct'] ?? 0)] ?? '') ?></p><?php endif; ?>
                <?php if (!$full && $q['type'] === 'numeric'): ?><p><span class="muted">Correct answer:</span> <?= e($q['data']['answer'] ?? '') ?> <?= e($q['data']['unit'] ?? '') ?></p><?php endif; ?>
                <?php if (!$full && $q['type'] === 'grammar_fill'): ?><p><span class="muted">Accepted:</span> <?= e(implode(' / ', (array) ($q['data']['accepted'] ?? []))) ?></p><?php endif; ?>
                <?php if (!empty($ans['feedback'])): ?><p class="result-q__feedback"><?= nl2br(e($ans['feedback'])) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
