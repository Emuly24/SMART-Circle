<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Links for sharing a page. When a share address is set (the Cloudflare Worker in tools/cloudflare), links go
 * through it so WhatsApp, Facebook and others can show a preview card; people are sent straight on to the page.
 */
final class ShareLinks
{
    /** The address to share for a site path; an optional title rides along for the preview. Pure. */
    public static function url(string $shareBase, string $siteBase, string $path, string $title = ''): string
    {
        $base = rtrim($shareBase !== '' ? $shareBase : $siteBase, '/');
        $url = $base . '/' . ltrim($path, '/');
        if ($title !== '' && $shareBase !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . rawurlencode(mb_substr($title, 0, 120));
        }

        return $url;
    }

    /** @return array<string, string> network => share link. Pure. */
    public static function buttons(string $url, string $text): array
    {
        $u = rawurlencode($url);
        $t = rawurlencode($text);

        return [
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($text . ' ' . $url),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $u,
            'x' => 'https://twitter.com/intent/tweet?text=' . $t . '&url=' . $u,
            'telegram' => 'https://t.me/share/url?url=' . $u . '&text=' . $t,
        ];
    }
}
