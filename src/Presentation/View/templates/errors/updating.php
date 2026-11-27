<?php /** @var bool $guest */ ?>
<div class="card card--narrow center">
    <h1>SMART Circle is being updated</h1>
    <p>New features are being added. Please try again in a few minutes. Nothing you have done is lost.</p>
    <?php if ($guest): ?><p class="muted"><a href="/login">Admin? Log in</a></p>
    <?php else: ?><form method="post" action="/logout" class="inline-form"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Log out</button></form><?php endif; ?>
</div>
