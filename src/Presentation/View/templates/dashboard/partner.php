<?php
/** @var \SmartCircle\Presentation\Http\Context $ctx */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <p class="eyebrow">School partner</p>
        <h1>Welcome, <?= e((string) ($user['full_name'] ?? '')) ?></h1>
        <p class="lead">Thank you for supporting your students. Topic tracking and your school's summary will appear here.</p>
    </div>
</section>
