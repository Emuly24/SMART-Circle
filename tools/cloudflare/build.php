<?php

declare(strict_types=1);

/*
 * Builds share-worker.js from share-worker.src.js with the share card (public/assets/brand/share-card.png)
 * inside it, so the Worker needs nothing else. Run after changing the card or the source:
 *   php tools/cloudflare/build.php
 */

$dir = __DIR__;
$card = dirname($dir, 2) . '/public/assets/brand/share-card.png';
$src = (string) file_get_contents($dir . '/share-worker.src.js');
$out = str_replace('__SHARE_CARD_PNG_BASE64__', base64_encode((string) file_get_contents($card)), $src);
file_put_contents($dir . '/share-worker.js', "// Built by tools/cloudflare/build.php from share-worker.src.js. Do not edit by hand.\n" . $out);
echo 'Wrote tools/cloudflare/share-worker.js (' . strlen($out) . " bytes)\n";
