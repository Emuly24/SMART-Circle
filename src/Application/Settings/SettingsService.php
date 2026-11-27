<?php

declare(strict_types=1);

namespace SmartCircle\Application\Settings;

use SmartCircle\Data\Repositories\SettingsRepository;
use SmartCircle\Support\PhoneNumber;

/** Programme settings the admin can change: the tutor's WhatsApp number and the WhatsApp community link. */
final class SettingsService
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function tutorWhatsapp(): ?string
    {
        $v = $this->settings->get('tutor_whatsapp');

        return $v !== null && $v !== '' ? $v : null;
    }

    public function community(): ?string
    {
        $v = $this->settings->get('whatsapp_community');

        return $v !== null && $v !== '' ? $v : null;
    }

    /** A wa.me link to the tutor, optionally with a message. */
    public function tutorWhatsappLink(string $text = ''): ?string
    {
        $n = $this->tutorWhatsapp();

        return $n === null ? null : 'https://wa.me/' . ltrim($n, '+') . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    /**
     * Validates settings. Pure.
     * @param array<string, string> $in
     * @return array{errors: array<string, string>, clean: array<string, string>}
     */
    public static function validate(array $in): array
    {
        $errors = [];
        $phone = trim($in['tutor_whatsapp'] ?? '');
        $clean = ['tutor_whatsapp' => '', 'whatsapp_community' => ''];
        if ($phone !== '') {
            $n = PhoneNumber::normalize($phone);
            if ($n === null) {
                $errors['tutor_whatsapp'] = 'Use a Malawian number like 0881 193 707.';
            } else {
                $clean['tutor_whatsapp'] = $n;
            }
        }
        $url = trim($in['whatsapp_community'] ?? '');
        if ($url !== '') {
            if (!preg_match('#^https://(chat\.whatsapp\.com|whatsapp\.com/channel)/[A-Za-z0-9_-]+$#', $url)) {
                $errors['whatsapp_community'] = 'Paste the invite link WhatsApp gives you (it starts with https://chat.whatsapp.com/).';
            } else {
                $clean['whatsapp_community'] = $url;
            }
        }

        return ['errors' => $errors, 'clean' => $clean];
    }

    /** @return array<string, string> errors */
    public function save(array $in): array
    {
        $v = self::validate($in);
        if ($v['errors'] === []) {
            foreach ($v['clean'] as $k => $val) {
                $this->settings->set($k, $val);
            }
        }

        return $v['errors'];
    }
}
