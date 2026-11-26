<?php
/**
 * Encouraging results: Got it / Partly there / Not yet, what went well, where it went off track,
 * the next step, and a way to ask for a re-check.
 * @var array<string, mixed> $r
 * @var array<string, string> $names
 * @var array<int, list<array<string, mixed>>> $rechecks question id => history
 * @var bool $canRecheck
 * @var int $windowDays
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
use SmartCircle\Application\Assessments\Feedback;
use SmartCircle\Presentation\View\Icons;

$a = $r['assessment'];
$s = $r['submission'];
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
$pct = (float) $s['max_score'] > 0 ? (int) round(100 * (float) $s['score'] / (float) $s['max_score']) : 0;
$count = ['got_it' => 0, 'partly' => 0, 'not_yet' => 0];
foreach ($r['questions'] as $q) {
    $ans = $r['answers'][(int) $q['id']] ?? [];
    $count[Feedback::status((float) ($ans['manual_score'] ?? $ans['auto_score'] ?? 0), (float) $q['max_marks'])]++;
}
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="<?= $a['note_id'] ? '/notes/' . e($a['note_id']) : '/assessments' ?>"><?= $a['note_id'] ? e($a['note_title'] ?? 'Notes') : 'Exams &amp; assignments' ?></a></p>
        <h1><?= e($a['title']) ?></h1>
        <div class="card result-card">
            <p class="result-card__headline"><?= e(Feedback::headline((float) $s['score'], (float) $s['max_score'])) ?></p>
            <p class="result-card__score"><strong><?= e($fmt($s['score'])) ?></strong> / <?= e($fmt($s['max_score'])) ?> <span class="muted">(<?= e($pct) ?>%)</span></p>
            <p class="status-strip">
                <?php foreach (Feedback::STATUSES as $key => [$symbol, $label]): if ($count[$key] === 0) { continue; } ?>
                    <span class="fb fb--<?= e($key) ?>"><?= Icons::feedback($key) ?> <?= e($label) ?>: <?= e($count[$key]) ?></span>
                <?php endforeach; ?>
            </p>
            <?php if ($s['feedback']): ?><p class="result-card__feedback"><?= nl2br(e($s['feedback'])) ?></p><?php endif; ?>
        </div>

        <?php foreach ($r['questions'] as $i => $q):
            $qid = (int) $q['id'];
            $ans = $r['answers'][$qid] ?? [];
            $got = (float) ($ans['manual_score'] ?? $ans['auto_score'] ?? 0);
            $status = Feedback::status($got, (float) $q['max_marks']);
            [$symbol, $label] = Feedback::STATUSES[$status];
            $answer = (string) ($ans['answer'] ?? '');
            $shown = $q['type'] === 'mcq' && $answer !== '' ? (string) ($q['data']['options'][(int) $answer] ?? '') : $answer;
            $history = $rechecks[$qid] ?? [];
            $open = array_filter($history, static fn (array $h): bool => $h['status'] === 'open') !== []; ?>
            <div class="card result-q result-q--<?= e($status) ?>" id="q<?= e($qid) ?>">
                <p class="question-row__meta"><strong>Question <?= $i + 1 ?></strong>
                    <span class="fb fb--<?= e($status) ?>"><?= Icons::feedback($status) ?> <?= e($label) ?></span>
                    <span class="chip"><?= e($fmt($got)) ?> / <?= e($fmt($q['max_marks'])) ?></span></p>
                <p><?= nl2br(e($q['prompt'])) ?></p>
                <p><span class="muted">Your answer:</span> <?= $shown === '' ? '<em class="muted">No answer</em>' : nl2br(e($shown)) ?></p>
                <?php if ($status !== 'got_it'): ?>
                    <?php if ($q['type'] === 'mcq'): ?><p><span class="muted">The answer:</span> <?= e($q['data']['options'][(int) ($q['data']['correct'] ?? 0)] ?? '') ?></p><?php endif; ?>
                    <?php if ($q['type'] === 'numeric'): ?><p><span class="muted">The answer:</span> <?= e($q['data']['answer'] ?? '') ?> <?= e($q['data']['unit'] ?? '') ?></p><?php endif; ?>
                    <?php if ($q['type'] === 'grammar_fill'): ?><p><span class="muted">Accepted:</span> <?= e(implode(' / ', (array) ($q['data']['accepted'] ?? []))) ?></p><?php endif; ?>
                <?php endif; ?>
                <?php foreach (['did_well' => ['👍', 'What you did well'], 'off_track' => ['🔎', 'Where it went off track'], 'next_step' => ['➡️', 'Your next step']] as $key => [$icon, $title]): ?>
                    <?php if (!empty($ans[$key])): ?>
                        <div class="fb-note fb-note--<?= e($key) ?>"><span class="fb-note__title"><?= $icon ?> <?= e($title) ?></span><p><?= nl2br(e($ans[$key])) ?></p></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($ans['feedback'])): ?><div class="fb-note"><p><?= nl2br(e($ans['feedback'])) ?></p></div><?php endif; ?>

                <?php foreach ($history as $h): ?>
                    <div class="recheck recheck--<?= e($h['status']) ?>">
                        <p><strong>You asked for a re-check</strong> <span class="muted">· <?= e(date('j M', (int) strtotime((string) $h['created_at']))) ?></span><br><?= nl2br(e($h['reason'])) ?></p>
                        <?php if ($h['status'] === 'open'): ?>
                            <p class="muted">Waiting for the tutor’s reply.</p>
                        <?php else: ?>
                            <p><strong><?= $h['status'] === 'changed' ? 'Mark changed to ' . e($fmt($h['new_score'])) : 'Mark stays the same' ?></strong><br><?= nl2br(e($h['tutor_reply'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ($canRecheck && $status !== 'got_it' && !$open): ?>
                    <details class="recheck-ask">
                        <summary>I think my answer deserves another look</summary>
                        <form method="post" action="/assessments/<?= e($a['id']) ?>/recheck" class="form">
                            <?= $csrf->field() ?>
                            <input type="hidden" name="question_id" value="<?= e($qid) ?>">
                            <label class="field"><span class="field__label">Explain clearly why you think your answer is correct</span>
                                <textarea name="reason" rows="3" maxlength="2000" required placeholder="e.g. I wrote 80 km/h; I showed the unit in my working, which is the same as the answer."></textarea></label>
                            <div class="form-actions"><button type="submit" class="btn btn--small">Ask for a re-check</button></div>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($canRecheck): ?><p class="note">You can ask for a re-check within <?= e($windowDays) ?> days of getting your marks. Polite, clear reasons help everyone.</p><?php endif; ?>
    </div>
</section>
