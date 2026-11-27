<?php
/**
 * @var array<string, int> $weights
 * @var array<string, list<array{grade: string, min_percent: int, descriptor: string}>> $bands
 * @var array<string, string> $categories
 * @var array<string, string> $errors
 * @var array<string, mixed>|null $old
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$err = static fn (string $f): string => isset($errors[$f]) ? '<div class="alert alert--error" role="alert">' . e($errors[$f]) . '</div>' : '';
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/grades">Grades</a></p>
        <h1>Grade settings</h1>
        <form method="post" action="/studio/grades/settings" class="form">
            <?= $csrf->field() ?>
            <div class="card section-card">
                <h2 class="h3">How a term grade is made</h2>
                <p class="muted">Each kind of work is averaged, then weighted. The weights must add up to 100.</p>
                <?= $err('weights') ?>
                <div class="weights">
                    <?php foreach ($categories as $cat => $label): ?>
                        <label class="field"><span class="field__label"><?= e($label) ?> (%)</span>
                            <input type="number" name="weights[<?= e($cat) ?>]" value="<?= e($old['weights'][$cat] ?? $weights[$cat]) ?>" min="0" max="100"></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $err('bands') ?>
            <?php foreach ($bands as $scale => $list): ?>
                <div class="card section-card">
                    <h2 class="h3"><?= $scale === 'jce' ? 'JCE letters (Forms 1–2)' : 'MSCE points (Forms 3–4)' ?></h2>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>Grade</th><th>From (%)</th><th>Meaning</th></tr></thead>
                        <tbody>
                        <?php foreach ($list as $b): $k = $scale . ':' . $b['grade']; ?>
                            <tr><td><strong><?= e($b['grade']) ?></strong></td>
                                <td><input type="number" name="bands[<?= e($k) ?>][min]" value="<?= e($old['bands'][$k]['min'] ?? $b['min_percent']) ?>" min="0" max="100" class="band-input"></td>
                                <td><input type="text" name="bands[<?= e($k) ?>][descriptor]" value="<?= e($old['bands'][$k]['descriptor'] ?? $b['descriptor']) ?>" maxlength="40" class="band-input band-input--wide"></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php endforeach; ?>
            <div class="form-actions"><button type="submit" class="btn">Save settings</button></div>
        </form>
    </div>
</section>
