<?php
/**
 * @var array<string, mixed> $me
 * @var string $error
 * @var string $back
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<section class="section section--tight">
    <div class="container narrow">
        <p class="crumbs"><a href="/partner/reviews">Check notes</a></p>
        <h1>Your signature</h1>
        <p class="lead">When you approve notes, your name, school and this signature appear on them, so students and parents know a qualified teacher checked them.</p>
        <?php if ($error !== ''): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($me['signature_path'] !== null): ?>
            <div class="card signature-card"><img class="signature-img" src="/partner/signature/image" alt="Your current signature"><p class="muted">Your current signature. Notes you already approved keep the signature you used then.</p></div>
        <?php endif; ?>
        <form method="post" action="/partner/signature" enctype="multipart/form-data" class="form card js-signature-form"><?= $csrf->field() ?>
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <input type="hidden" name="drawn" value="" class="js-signature-data">
            <div class="signature-pad js-signature-pad" hidden>
                <p class="field__label">Sign with your finger or mouse</p>
                <canvas class="signature-pad__canvas js-signature-canvas" width="600" height="220" aria-label="Signature drawing area"></canvas>
                <p><button type="button" class="btn btn--small btn--ghost js-signature-clear">Clear</button></p>
                <p class="muted">Or upload a photo instead:</p>
            </div>
            <label class="field"><span class="field__label">Photo of your signature <span class="muted">(dark ink on white paper works best)</span></span>
                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></label>
            <label class="choice choice--block"><input type="checkbox" name="consent" value="1"> I agree that my name, school and signature are shown on notes I approve, including free handouts in the public library that anyone can read and print.</label>
            <div class="form-actions"><button type="submit" class="btn btn--green">Save my signature</button></div>
        </form>
    </div>
</section>
<script src="<?= e($asset('js/signature.js')) ?>" defer></script>
