<?php
/**
 * @var bool $isTutor
 * @var string $base
 * @var list<array<string, mixed>> $terms
 * @var array<string, mixed> $today
 * @var list<array<string, mixed>> $events
 * @var array<string, string> $kinds
 * @var array<string, string> $allKinds
 * @var int|null $schoolId
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<span class="field__error">' . e($errors[$f]) . '</span>' : '';
$d = static fn (string $date): string => date('D j M Y', (int) strtotime($date));
$years = [];
foreach ($terms as $t) {
    $years[$t['year_name']][] = $t;
}
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Academic calendar</h1>
        <p class="lead"><?= $isTutor
            ? 'The national calendar (harmonised for every school) and every school’s own dates. Terms make grades measurable.'
            : 'The national calendar, plus your school’s own dates such as mid-term breaks and mock exams.' ?></p>
        <?= $view->partial('term_strip', ['today' => $today]) ?>

        <?php foreach ($years as $name => $list): ?>
            <div class="card section-card">
                <h2 class="h3"><?= e($name) ?></h2>
                <ol class="term-list">
                    <?php foreach ($list as $i => $t):
                        $weeks = (int) ceil(((strtotime((string) $t['closes_on']) - strtotime((string) $t['opens_on'])) / 86400 + 1) / 7);
                        $next = $list[$i + 1] ?? null; ?>
                        <li class="term-row<?= ($today['term']['id'] ?? null) === $t['id'] && $today['in_term'] ? ' term-row--now' : '' ?>">
                            <strong>Term <?= e($t['number']) ?></strong>
                            <span><?= e($d((string) $t['opens_on'])) ?> to <?= e($d((string) $t['closes_on'])) ?></span>
                            <span class="chip"><?= e($weeks) ?> weeks</span>
                        </li>
                        <?php if ($next !== null): $hol = (int) round((strtotime((string) $next['opens_on']) - strtotime((string) $t['closes_on'])) / 86400 / 7); ?>
                            <li class="term-row term-row--holiday"><span>Holiday</span><span class="muted">about <?= e($hol) ?> week<?= $hol === 1 ? '' : 's' ?></span></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endforeach; ?>

        <div class="card section-card">
            <h2 class="h3">Important dates</h2>
            <?php if ($events === []): ?>
                <p class="muted">No dates yet.</p>
            <?php else: ?>
                <ul class="event-list">
                    <?php foreach ($events as $e): ?>
                        <li class="event event--<?= e($e['kind']) ?>">
                            <span class="event__when"><?= e(date('j M', (int) strtotime((string) $e['starts_on']))) ?><?= $e['ends_on'] !== $e['starts_on'] ? '–' . e(date('j M Y', (int) strtotime((string) $e['ends_on']))) : ' ' . e(date('Y', (int) strtotime((string) $e['starts_on']))) ?></span>
                            <span class="event__what"><strong><?= e($e['title']) ?></strong>
                                <span class="muted"><?= $e['school_id'] === null ? 'National' : e($e['school_name'] ?? 'School') ?><?= $e['forms'] ? ' · Form ' . e(str_replace(',', ', ', (string) $e['forms'])) : '' ?></span></span>
                            <?php if ($isTutor || ($e['school_id'] !== null && (int) $e['school_id'] === $schoolId)): ?>
                                <form method="post" action="<?= e($base) ?>/events/<?= e($e['id']) ?>/delete" class="js-confirm" data-confirm="Remove this date?"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Remove</button></form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e($base) ?>/events" class="form card question-form">
            <?= $csrf->field() ?>
            <h2 class="h3"><?= $isTutor ? 'Add a date' : 'Add your school’s date' ?></h2>
            <div class="field-row">
                <label class="field"><span class="field__label">What</span>
                    <select name="kind"><option value="">Choose…</option><?php foreach ($kinds as $k => $l): ?><option value="<?= e($k) ?>" <?= ($old['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('kind') ?></label>
                <label class="field"><span class="field__label">Title <span class="muted">(optional)</span></span><input type="text" name="title" value="<?= e($old['title'] ?? '') ?>" maxlength="150"></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">From</span><input type="date" name="starts_on" value="<?= e($old['starts_on'] ?? '') ?>"><?= $err('starts_on') ?></label>
                <label class="field"><span class="field__label">To <span class="muted">(leave empty for one day)</span></span><input type="date" name="ends_on" value="<?= e($old['ends_on'] ?? '') ?>"><?= $err('ends_on') ?></label>
            </div>
            <fieldset class="field"><legend class="field__label">Which forms <span class="muted">(none ticked = all)</span></legend>
                <div class="choice-row"><?php for ($f = 1; $f <= 4; $f++): ?><label class="choice"><input type="checkbox" name="forms[]" value="<?= $f ?>" <?= in_array((string) $f, (array) ($old['forms'] ?? []), true) ? 'checked' : '' ?>> Form <?= $f ?></label><?php endfor; ?></div></fieldset>
            <div class="form-actions"><button type="submit" class="btn btn--small">Add date</button></div>
        </form>

        <?php if ($isTutor): ?>
            <details class="card section-card"<?= isset($errors['name']) || array_filter(array_keys($errors), static fn ($k) => str_starts_with((string) $k, 't')) ? ' open' : '' ?>>
                <summary><strong>Add or correct an academic year</strong></summary>
                <form method="post" action="/studio/calendar/year" class="form question-form"><?= $csrf->field() ?>
                    <label class="field"><span class="field__label">Year</span><input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="2027/28" maxlength="7"><?= $err('name') ?></label>
                    <?php for ($n = 1; $n <= 3; $n++): ?>
                        <div class="field-row">
                            <label class="field"><span class="field__label">Term <?= $n ?> opens</span><input type="date" name="t<?= $n ?>_open" value="<?= e($old["t{$n}_open"] ?? '') ?>"><?= $err("t{$n}_open") ?></label>
                            <label class="field"><span class="field__label">Term <?= $n ?> closes</span><input type="date" name="t<?= $n ?>_close" value="<?= e($old["t{$n}_close"] ?? '') ?>"><?= $err("t{$n}_close") ?></label>
                        </div>
                    <?php endfor; ?>
                    <p class="note">Saving a year that already exists (same name) corrects its dates.</p>
                    <div class="form-actions"><button type="submit" class="btn btn--small">Save year</button></div>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>
<script src="<?= e($asset('js/confirm.js')) ?>" defer></script>
