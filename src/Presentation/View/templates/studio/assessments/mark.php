<?php
/**
 * @var array{submission: array<string, mixed>, questions: list<array<string, mixed>>, answers: array<int, array<string, mixed>>} $v
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var array<string, string> $types
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$s = $v['submission'];
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
$shown = static function (array $q, string $answer): string {
    if ($q['type'] === 'mcq') {
        $opts = (array) ($q['data']['options'] ?? []);
        return $answer === '' ? '' : (string) ($opts[(int) $answer] ?? $answer);
    }
    return $answer;
};
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/assessments/<?= e($s['assessment_id']) ?>/submissions">Submissions</a></p>
        <h1><?= e($s['full_name']) ?></h1>
        <p class="note">Mark to encourage: students see ✓ Got it, ◐ Partly there or ↻ Not yet, never a red cross. Say what went well, where it went off track, and the next step.</p>
        <p class="lead"><?= e($s['assessment_title']) ?><?= $s['score'] !== null ? ' · ' . e($fmt($s['score'])) . ' / ' . e($fmt($s['max_score'])) : '' ?></p>
        <?php if ($errors !== []): ?><div class="alert alert--error" role="alert">Some marks are missing or too high. See below.</div><?php endif; ?>
        <form method="post" action="/studio/submissions/<?= e($s['id']) ?>" class="form">
            <?= $csrf->field() ?>
            <?php foreach ($v['questions'] as $i => $q):
                $qid = (int) $q['id'];
                $ans = $v['answers'][$qid] ?? [];
                $auto = $ans['auto_score'] ?? null;
                $typed = $old['score'][$qid] ?? ($ans['manual_score'] ?? '');
                $answer = (string) ($ans['answer'] ?? ''); ?>
                <div class="card mark-q<?= $auto === null ? ' mark-q--manual' : '' ?>">
                    <p class="question-row__meta"><strong>Q<?= $i + 1 ?></strong> <span class="chip"><?= e($types[$q['type']] ?? '') ?></span>
                        <span class="chip"><?= e($fmt($q['max_marks'])) ?> marks</span></p>
                    <p class="mark-q__prompt"><?= nl2br(e($q['prompt'])) ?></p>
                    <div class="mark-q__answer"><span class="muted">Answer:</span> <?= $answer === '' ? '<em class="muted">No answer</em>' : nl2br(e($shown($q, $answer))) ?></div>
                    <?php if ($q['type'] === 'short_text' && !empty($q['data']['model_answer'])): ?>
                        <p class="mark-q__model"><span class="muted">Model answer:</span> <?= nl2br(e($q['data']['model_answer'])) ?></p>
                    <?php elseif ($q['type'] === 'mcq'): ?>
                        <p class="mark-q__model"><span class="muted">Correct:</span> <?= e($q['data']['options'][(int) ($q['data']['correct'] ?? 0)] ?? '') ?></p>
                    <?php elseif ($q['type'] === 'numeric'): ?>
                        <p class="mark-q__model"><span class="muted">Correct:</span> <?= e($q['data']['answer'] ?? '') ?> <?= e($q['data']['unit'] ?? '') ?></p>
                    <?php endif; ?>
                    <div class="field-row">
                        <label class="field"><span class="field__label">Mark <?= $auto !== null ? '<span class="muted">(automatic: ' . e($fmt($auto)) . '; leave empty to keep)</span>' : '' ?></span>
                            <input type="number" name="score[<?= $qid ?>]" value="<?= e($typed === null || $typed === '' ? '' : $fmt($typed)) ?>" min="0" max="<?= e($fmt($q['max_marks'])) ?>" step="0.5">
                            <?= isset($errors[(string) $qid]) ? '<span class="field__error">' . e($errors[(string) $qid]) . '</span>' : '' ?></label>
                    </div>
                    <div class="feedback-fields">
                        <?php foreach (['did_well' => ['What you did well', 'e.g. You chose the right formula and showed every step.'], 'off_track' => ['Where it went off track', 'e.g. In step 3 the time must be in hours, not minutes.'], 'next_step' => ['Your next step', 'e.g. Practise two more conversions, then try Question 4 again.']] as $key => [$label, $hint]): ?>
                            <label class="field"><span class="field__label"><?= e($label) ?> <span class="muted">(optional)</span></span>
                                <input type="text" name="feedback[<?= e($key) ?>][<?= $qid ?>]" value="<?= e($old['feedback'][$key][$qid] ?? ($ans[$key] ?? '')) ?>" maxlength="2000" placeholder="<?= e($hint) ?>"></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="card">
                <label class="field"><span class="field__label">Overall comment <span class="muted">(optional)</span></span>
                    <textarea name="overall" rows="3"><?= e($old['overall'] ?? ($s['feedback'] ?? '')) ?></textarea></label>
                <div class="form-actions"><button type="submit" class="btn">Save marks</button></div>
            </div>
        </form>
    </div>
</section>
