<?php

declare(strict_types=1);

/*
 * Builds the two Workers from share-worker.src.js. Run after changing the card or the source:
 *   php tools/cloudflare/build.php
 *  - share-worker.js:      the share card is inside it (about 110 KB). Needs nothing else.
 *  - share-worker-lite.js: about 5 KB, easy to paste on a phone or tablet. It points preview robots to the
 *    copy of the card in the public GitHub repository.
 */

$dir = __DIR__;
$card = dirname($dir, 2) . '/public/assets/brand/share-card.png';
$cardUrl = 'https://raw.githubusercontent.com/Emuly24/SMART-Circle/main/public/assets/brand/share-card.png';
$src = (string) file_get_contents($dir . '/share-worker.src.js');
$head = "// Built by tools/cloudflare/build.php from share-worker.src.js. Do not edit by hand.\n";

$full = str_replace(['__SHARE_CARD_PNG_BASE64__', '__CARD_URL__'], [base64_encode((string) file_get_contents($card)), ''], $src);
file_put_contents($dir . '/share-worker.js', $head . $full);
$lite = str_replace(['__SHARE_CARD_PNG_BASE64__', '__CARD_URL__'], ['', $cardUrl], $src);
file_put_contents($dir . '/share-worker-lite.js', $head . $lite);
echo 'Wrote share-worker.js (' . strlen($full) . ' bytes) and share-worker-lite.js (' . strlen($lite) . " bytes)\n";
