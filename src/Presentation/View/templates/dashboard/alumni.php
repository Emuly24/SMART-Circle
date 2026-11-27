<?php
/** @var \SmartCircle\Presentation\Http\Context $ctx */
$user = $ctx->user() ?? [];
?>
<section class="section">
    <div class="container">
        <p class="eyebrow">SMART Circle alumni</p>
        <h1>Welcome back, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="lead">You are part of SMART Circle for good. Tell us how you are doing, ask for help with what comes next, and help those coming after you.</p>
        <div class="tile-grid">
            <a class="tile tile--link" href="/alumni"><h2>What I am doing now</h2><p>University, college, work, waiting for selection: tell us, and say what help you need.</p><span class="tag tag--ready">Update</span></a>
            <a class="tile tile--link" href="/alumni/community"><h2>Community</h2><p>Questions, opportunities and stories from SMART Circle alumni.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/results/mine"><h2>MANEB results</h2><p>Record your results; you choose who sees them.</p><span class="tag tag--ready">Open</span></a>
            <a class="tile tile--link" href="/office-hours"><h2>Office hours</h2><p>Book a call about selection, loans, applications or courses.</p><span class="tag tag--ready">Book</span></a>
            <a class="tile tile--link" href="/messages"><h2>Messages</h2><p>Write to the tutor at any time.</p><span class="tag tag--ready">Open</span></a>
        </div>
        <?= $view->partial('community') ?>
    </div>
</section>
