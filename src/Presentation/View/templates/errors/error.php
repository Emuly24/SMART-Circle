<?php
/**
 * @var int $status
 * @var string $message
 * @var string $detail
 */
?>
<section class="section">
    <div class="container narrow center">
        <p class="error-code"><?= e($status) ?></p>
        <h1><?= e($message) ?></h1>
        <?php if ($detail !== ''): ?>
            <pre class="debug"><?= e($detail) ?></pre>
        <?php endif; ?>
        <p><a class="btn" href="/">Back to home</a></p>
    </div>
</section>
