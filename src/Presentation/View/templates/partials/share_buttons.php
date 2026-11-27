<?php
/**
 * Share this page: WhatsApp, Facebook, X, Telegram, copy the link, or the phone's own share menu.
 * @var string $shareUrl
 * @var string $shareText
 * @var string|null $label
 */
$links = \SmartCircle\Support\ShareLinks::buttons($shareUrl, $shareText);
?>
<div class="share js-share" data-url="<?= e($shareUrl) ?>" data-text="<?= e($shareText) ?>">
    <p class="share__label"><?= e($label ?? 'Share with a friend, parent or teacher') ?></p>
    <div class="share__buttons">
        <button type="button" class="share__btn share__btn--native js-share-native" hidden>Share…</button>
        <a class="share__btn share__btn--whatsapp" href="<?= e($links['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <a class="share__btn share__btn--facebook" href="<?= e($links['facebook']) ?>" target="_blank" rel="noopener">Facebook</a>
        <a class="share__btn share__btn--x" href="<?= e($links['x']) ?>" target="_blank" rel="noopener">X</a>
        <a class="share__btn share__btn--telegram" href="<?= e($links['telegram']) ?>" target="_blank" rel="noopener">Telegram</a>
        <button type="button" class="share__btn js-share-copy">Copy link</button>
    </div>
    <p class="share__done muted js-share-done" hidden role="status">Link copied.</p>
</div>
