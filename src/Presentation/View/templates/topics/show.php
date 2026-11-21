<?php
/**
 * @var array<string, mixed> $topic
 * @var array<string, mixed>|null $parent
 * @var list<array<string, mixed>> $children
 * @var list<array<string, mixed>> $lessons
 * @var array<string, mixed>|null $request
 * @var list<array<string, mixed>> $books
 * @var string|null $schoolCoverage
 * @var bool $isStudent
 * @var array<string, string> $names
 * @var array<string, string> $struggles
 * @var array<string, string> $statuses
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$subject = (string) $topic['subject'];
$first = $request === null;
$mine = $request !== null && !empty($request['joined']);
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs">
            <a href="/topics?subject=<?= e($subject) ?>&amp;form=<?= e($topic['form']) ?>">Form <?= e($topic['form']) ?> <?= e($names[$subject]) ?></a>
            <?php if ($parent !== null): ?> › <a href="/topics/<?= e($parent['id']) ?>"><?= e($parent['title']) ?></a><?php endif; ?>
        </p>
        <p><span class="badge badge--<?= e($subject) ?>"><?= e($names[$subject]) ?></span>
            <?php if ($topic['unit']): ?><span class="muted"> · <?= e($topic['unit']) ?></span><?php endif; ?></p>
        <h1><?= e($topic['title']) ?></h1>
        <?php if ($topic['objectives']): ?><p class="lead"><?= nl2br(e($topic['objectives'])) ?></p><?php endif; ?>

        <?php if ($children !== []): ?>
            <h2 class="h3">In this topic</h2>
            <ul class="subtopics subtopics--flat">
                <?php foreach ($children as $c): ?>
                    <li><a href="/topics/<?= e($c['id']) ?>"><?= e($c['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($books !== []): ?>
            <div class="card book-refs">
                <h2 class="h3">Read it in your textbook</h2>
                <ul>
                    <?php foreach ($books as $b): ?>
                        <li>
                            <strong><?= e($b['title']) ?></strong><?= $b['edition'] !== '' ? ' <span class="muted">(' . e($b['edition']) . ')</span>' : '' ?>
                            <?php if ($b['at_my_school']): ?><span class="chip chip--lesson">Used at your school</span><?php endif; ?>
                            <br><span class="book-refs__where"><?= $b['chapter'] !== '' ? 'Chapter ' . e($b['chapter']) : '' ?><?= $b['chapter'] !== '' && $b['pages'] !== '' ? ' · ' : '' ?><?= $b['pages'] !== '' ? 'pages ' . e($b['pages']) : '' ?><?= !$b['exact'] ? ' <span class="muted">(for: ' . e($b['topic_title']) . ')</span>' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($lessons !== []): ?>
            <h2 class="h3">Lessons on this topic</h2>
            <div class="lesson-grid">
                <?php foreach ($lessons as $l): ?>
                    <a class="lesson-card" href="/lessons/<?= e($l['id']) ?>">
                        <span class="lesson-card__title"><?= e($l['title']) ?></span>
                        <span class="lesson-card__info"><?= e(gmdate('i:s', intdiv((int) $l['duration_ms'], 1000))) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($request !== null): ?>
            <div class="card request-state request-state--<?= e($request['status']) ?>">
                <p class="request-state__label">
                    <?= e($request['exception_status'] === 'pending' ? 'Waiting for the tutor to approve' : ($statuses[$request['status']] ?? $request['status'])) ?>
                </p>
                <p><?= e($request['voice_count']) ?> <?= (int) $request['voice_count'] === 1 ? 'student has' : 'students have' ?> asked for this topic<?= $mine ? ', including you' : '' ?>.</p>
                <?php if ($request['exception_status'] === 'declined'): ?>
                    <p class="muted">The tutor will teach this once schools have covered it. If your school has now taught it, ask again below.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isStudent): ?>
            <form method="post" action="/topics/<?= e($topic['id']) ?>/request" class="form card ask-form" id="ask">
                <?= $csrf->field() ?>
                <h2 class="h3"><?= $first ? 'I’m stuck on this topic' : ($mine ? 'Change how you are stuck' : 'Me too: I’m stuck on this') ?></h2>
                <?= $err('topic') ?>
                <fieldset class="field">
                    <legend class="field__label">What is hard about it?</legend>
                    <div class="choice-grid choice-grid--wide">
                        <?php foreach ($struggles as $key => $label): ?>
                            <label class="choice"><input type="radio" name="struggle_type" value="<?= e($key) ?>" <?= ($old['struggle_type'] ?? '') === $key ? 'checked' : '' ?> required> <?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <?= $err('struggle_type') ?>
                </fieldset>
                <label class="field">
                    <span class="field__label">Where exactly do you get lost? <?= $first ? '' : '<span class="muted">(optional)</span>' ?></span>
                    <textarea name="details" rows="3" maxlength="1000" placeholder="e.g. I can balance simple equations but get lost when there are brackets."><?= e($old['details'] ?? '') ?></textarea>
                    <?= $err('details') ?>
                </label>
                <?php if (in_array($schoolCoverage, ['covered', 'covering'], true)): ?>
                    <p class="note">Your school has told us it <?= $schoolCoverage === 'covered' ? 'has taught' : 'is teaching' ?> this topic.</p>
                    <input type="hidden" name="covered" value="yes">
                <?php else: ?>
                <fieldset class="field">
                    <legend class="field__label">Has your school taught this topic yet?</legend>
                    <div class="choice-row">
                        <label class="choice"><input type="radio" name="covered" value="yes" <?= ($old['covered'] ?? '') === 'yes' ? 'checked' : '' ?> required> Yes, we have covered it</label>
                        <label class="choice"><input type="radio" name="covered" value="no" class="js-not-covered" <?= ($old['covered'] ?? '') === 'no' ? 'checked' : '' ?>> Not yet</label>
                    </div>
                    <?= $err('covered') ?>
                </fieldset>
                <label class="field js-exception-reason">
                    <span class="field__label">If not yet: why do you need it now?</span>
                    <textarea name="exception_reason" rows="2" maxlength="1000" placeholder="e.g. Our school has no Chemistry teacher this term."><?= e($old['exception_reason'] ?? '') ?></textarea>
                    <span class="muted field__hint">SMART Circle follows your school. Topics not yet taught need the tutor’s approval.</span>
                    <?= $err('exception_reason') ?>
                </label>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn"><?= $first ? 'Ask for this topic' : 'Add my voice' ?></button>
                </div>
            </form>
            <script src="<?= e($asset('js/ask.js')) ?>" defer></script>
        <?php endif; ?>
    </div>
</section>
