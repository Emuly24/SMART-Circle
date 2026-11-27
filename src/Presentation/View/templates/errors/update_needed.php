<?php
/**
 * Shown to the admin when new code is waiting for its database updates.
 * @var list<string> $pending
 */
?>
<div class="card card--narrow">
    <h1>One step to finish the update</h1>
    <p>New features have arrived, and the database needs <?= count($pending) === 1 ? 'one update' : e(count($pending)) . ' updates' ?> before the site can be used again. This keeps all existing data and takes a few seconds.</p>
    <form method="post" action="/dashboard/database-update" class="form">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn--green btn--block">Update the database now</button>
    </form>
    <details><summary class="muted">What will be applied</summary><ul><?php foreach ($pending as $p): ?><li><code><?= e($p) ?></code></li><?php endforeach; ?></ul></details>
    <p class="muted">Until then, students and teachers see a short “being updated” notice.</p>
    <form method="post" action="/logout" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Log out</button></form>
</div>
