<?php
/**
 * The page a guardian opens from the message SMART Circle sends them. No login.
 * @var array<string, mixed> $g first, lang, status
 * @var string $lang
 * @var array<string, string> $w wording in their language
 * @var string $token
 * @var string|null $answered yes | no
 */
$other = $lang === 'ny' ? 'en' : 'ny';
?>
<div class="card guardian-confirm" lang="<?= e($lang) ?>">
    <h1><?= e($w['title']) ?></h1>
    <?php if ($answered !== null): ?>
        <p class="lead"><?= e($answered === 'yes' ? $w['thanks_yes'] : $w['thanks_no']) ?></p>
    <?php else: ?>
        <p class="lead"><?= e($w['lead']) ?></p>
        <p><?= e($w['support']) ?></p>
        <form method="post" action="/g/<?= e($token) ?>" class="form">
            <?= $csrf->field() ?>
            <input type="hidden" name="lang" value="<?= e($lang) ?>">
            <button type="submit" name="answer" value="yes" class="btn btn--green btn--block"><?= e($w['yes']) ?></button>
            <button type="submit" name="answer" value="no" class="btn btn--ghost btn--block"><?= e($w['no']) ?></button>
        </form>
    <?php endif; ?>
    <p class="muted"><a href="/g/<?= e($token) ?>?lang=<?= e($other) ?>"><?= e($w['switch']) ?></a> · <a href="/about">SMART Circle</a></p>
</div>
