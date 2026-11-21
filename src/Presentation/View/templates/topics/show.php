<?php
/**
 * @var array<string, mixed> $topic
 * @var array<string, mixed>|null $parent
 * @var list<array<string, mixed>> $children
 * @var list<array<string, mixed>> $lessons
 * @var array<string, mixed>|null $request
 * @var list<array<string, mixed>> $books
 * @var array{open: bool, basis: ?string, state: string} $access
 * @var array<string, string> $states
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
                    <?= e($statuses[$request['status']] ?? $request['status']) ?>
                </p>
                <p><?= e($request['voice_count']) ?> <?= (int) $request['voice_count'] === 1 ? 'student has' : 'students have' ?> asked for this topic<?= $mine ? ', including you' : '' ?>.</p>
            </div>
        <?php endif; ?>

        <?php if ($isStudent):
            $state = $access['state'];
            $reportForm = static fn (string $status, string $label, string $class) => '<form method="post" action="/topics/' . e($topic['id']) . '/report" class="inline-form">'
                . $csrf->field() . '<input type="hidden" name="status" value="' . e($status) . '"><button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>'; ?>
            <div class="card access-card access-card--<?= $access['open'] ? 'open' : 'locked' ?>">
                <p class="access-card__state"><?= $access['open'] ? '✓' : '🔒' ?> <?= e($states[$state] ?? '') ?></p>
                <?php if ($state === 'school'): ?>
                    <p>Your school has reported this topic as taught, so its lessons are open to you.</p>
                    <p class="access-card__actions"><?= $reportForm('not_yet', 'My class hasn’t reached it yet', 'btn btn--small btn--ghost') ?></p>
                <?php elseif ($state === 'self_unverified' || $state === 'self_confirmed'): ?>
                    <p>You told us your class has covered this topic.<?= $state === 'self_unverified' ? ' The tutor will check with your school.' : '' ?></p>
                    <?php if ($state === 'self_unverified'): ?><p class="access-card__actions"><?= $reportForm('clear', 'Undo: we haven’t covered it', 'btn btn--small btn--ghost') ?></p><?php endif; ?>
                <?php elseif ($state === 'exception'): ?>
                    <p>The tutor opened this topic early for you.</p>
                <?php elseif ($state === 'class_not_yet'): ?>
                    <p>You told us your class hasn’t reached this topic. It stays closed for you until it does.</p>
                    <p class="access-card__actions"><?= $reportForm('clear', 'My class has now covered it', 'btn btn--small') ?></p>
                <?php elseif ($state === 'awaiting_check'): ?>
                    <p>You said your class has covered it, but your school’s teacher hasn’t reported it yet. The tutor will check and it will open if confirmed.</p>
                    <p class="access-card__actions"><?= $reportForm('clear', 'Undo my answer', 'btn btn--small btn--ghost') ?></p>
                <?php elseif ($state === 'exception_pending'): ?>
                    <p>You asked to open this topic early. The tutor will decide soon.</p>
                <?php else: ?>
                    <p>SMART Circle goes over what your own school has already taught. This topic opens when your school reports it, and you will be notified.</p>
                    <p class="access-card__actions"><?= $reportForm('covered', 'My class has already covered it', 'btn btn--small') ?></p>
                <?php endif; ?>
            </div>

            <?php if ($access['open'] || in_array($state, ['not_taught', 'unknown', 'class_not_yet'], true)): ?>
            <form method="post" action="/topics/<?= e($topic['id']) ?>/request" class="form card ask-form" id="ask">
                <?= $csrf->field() ?>
                <?php if ($access['open']): ?>
                    <h2 class="h3"><?= $first ? 'I’m stuck on this topic' : ($mine ? 'Change how you are stuck' : 'Me too: I’m stuck on this') ?></h2>
                <?php else: ?>
                    <h2 class="h3">Need it before your school teaches it?</h2>
                    <p class="muted">For real reasons only, for example no teacher for this subject this term. The tutor decides.</p>
                <?php endif; ?>
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
                <?php if (!$access['open']): ?>
                    <label class="field">
                        <span class="field__label">Why do you need it now?</span>
                        <textarea name="exception_reason" rows="2" maxlength="1000" placeholder="e.g. Our school has no Chemistry teacher this term." required><?= e($old['exception_reason'] ?? '') ?></textarea>
                        <?= $err('exception_reason') ?>
                    </label>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn"><?= !$access['open'] ? 'Ask to open early' : ($first ? 'Ask for this topic' : 'Add my voice') ?></button>
                </div>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
