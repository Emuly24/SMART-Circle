<?php
/** @var \SmartCircle\Presentation\Http\Context $ctx */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <h1>Hello, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">Your lessons, topic requests, exams and discussions will appear here.</p>
        <p><a class="btn" href="/lessons">Watch lessons</a></p>
    </div>
</section>
