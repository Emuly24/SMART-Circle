<?php
/** @var array<string, array<string, mixed>> $templates */
$channels = ['whatsapp' => 'WhatsApp / SMS / email', 'email' => 'Email', 'notification' => 'Notification (and email)'];
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/settings">Settings</a></p>
        <h1>Message templates</h1>
        <p class="lead">Change the wording of ready-made messages. Words in {{double braces}} are filled in for each person. You can always go back to the default.</p>
        <ul class="template-list">
            <?php foreach ($templates as $key => $t): ?>
                <li><a class="card section-card community-card" href="/studio/templates/<?= e($key) ?>"><span><strong><?= e($t['name']) ?></strong><br>
                    <span class="muted"><?= e($channels[$t['channel']] ?? $t['channel']) ?> · <?= $t['custom'] ? 'Your wording' : 'Default wording' ?></span></span></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
