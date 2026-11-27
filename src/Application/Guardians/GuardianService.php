<?php

declare(strict_types=1);

namespace SmartCircle\Application\Guardians;

use SmartCircle\Application\Notifications\NotificationService;
use SmartCircle\Data\Repositories\GuardianRepository;
use SmartCircle\Data\Repositories\OverviewRepository;
use SmartCircle\Support\PhoneNumber;

/**
 * Guardians are told after admission, in the language they understand, and confirm with a link
 * (or the tutor records a call). Every message the tutor sends is logged in the contact log.
 */
final class GuardianService
{
    public function __construct(
        private readonly GuardianRepository $guardians,
        private readonly OverviewRepository $contacts,
        private readonly NotificationService $notifications,
        private readonly string $baseUrl,
        /** @var \Closure(): ?string */
        private readonly \Closure $tutorPhone,
    ) {
    }

    /** @return list<array<string, mixed>> every active student's guardian, with status */
    public function all(): array
    {
        $sent = $this->guardians->monthlySent(date('Y-m-01 00:00:00'));

        return array_map(fn (array $g): array => $g + ['monthly_sent' => $sent[(int) $g['user_id']] ?? null, 'status' => self::status($g)], $this->guardians->all());
    }

    /** confirmed | declined | waiting. Pure. @param array<string, mixed> $g */
    public static function status(array $g): string
    {
        if ($g['guardian_declined_at'] !== null) {
            return 'declined';
        }

        return $g['guardian_confirmed_at'] !== null ? 'confirmed' : 'waiting';
    }

    /**
     * Everything the tutor needs to contact one guardian: filled-in messages and one-tap links.
     * @return array<string, mixed>|null
     */
    public function kit(int $userId, string $tutorName): ?array
    {
        $g = $this->guardians->find($userId);
        if ($g === null) {
            return null;
        }
        $token = $g['guardian_token'] ?? null;
        if ($token === null || $token === '') {
            $token = bin2hex(random_bytes(12));
            $this->guardians->setToken($userId, $token);
        }
        $lang = isset(GuardianMessages::LANGUAGES[(string) $g['guardian_language']]) ? (string) $g['guardian_language'] : 'en';
        $first = explode(' ', trim((string) $g['full_name']))[0];
        $d = [
            'guardian' => trim((string) ($g['guardian_name'] ?? '')) ?: ($lang === 'ny' ? 'a makolo' : 'parent'),
            'child' => (string) $g['full_name'],
            'child_first' => $first,
            'form' => (string) $g['form'],
            'school' => (string) ($g['school_name'] ?? ''),
            'link' => rtrim($this->baseUrl, '/') . '/g/' . $token,
            'tutor' => $tutorName,
            'tutor_phone' => ($p = ($this->tutorPhone)()) !== null ? PhoneNumber::display($p) : '',
        ];
        [$lessons, $work] = $this->guardians->monthStats($userId, date('Y-m-01 00:00:00'), date('Y-m-d H:i:s', strtotime('first day of next month 00:00')));
        $admission = GuardianMessages::admission($lang, $d);
        $monthly = GuardianMessages::monthly($lang, $d, $lessons, $work, (int) date('n'));

        return [
            'g' => $g + ['status' => self::status($g)],
            'lang' => $lang,
            'admission' => $admission,
            'monthly' => $monthly,
            'call' => GuardianMessages::callScript($lang, $first),
            'links' => self::links($g, $admission, $monthly, GuardianMessages::admissionSubject($lang, $first)),
            'stats' => ['lessons' => $lessons, 'work' => $work],
        ];
    }

    /**
     * One-tap links. Pure. WhatsApp goes to the WhatsApp number if there is one, else the call number.
     * @param array<string, mixed> $g
     * @return array<string, string|null>
     */
    public static function links(array $g, string $admission, string $monthly, string $subject): array
    {
        $call = PhoneNumber::normalize((string) ($g['guardian_phone'] ?? ''));
        $wa = PhoneNumber::normalize((string) ($g['guardian_whatsapp'] ?? '')) ?? $call;
        $email = (string) ($g['guardian_email'] ?? '');
        $valid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        return [
            'whatsapp' => $wa !== null ? 'https://wa.me/' . ltrim($wa, '+') . '?text=' . rawurlencode($admission) : null,
            'sms' => $call !== null ? 'sms:' . $call . '?body=' . rawurlencode($admission) : null,
            'tel' => $call !== null ? 'tel:' . $call : null,
            'email' => $valid ? 'mailto:' . $email . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($admission) : null,
            'monthly_whatsapp' => $wa !== null ? 'https://wa.me/' . ltrim($wa, '+') . '?text=' . rawurlencode($monthly) : null,
            'monthly_sms' => $call !== null ? 'sms:' . $call . '?body=' . rawurlencode($monthly) : null,
        ];
    }

    /* ---------- The guardian's link ---------- */

    /** @return array<string, mixed>|null */
    public function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
            return null;
        }
        $g = $this->guardians->byToken($token);
        if ($g === null) {
            return null;
        }

        return ['first' => explode(' ', trim((string) $g['full_name']))[0], 'lang' => (string) $g['guardian_language'], 'user_id' => (int) $g['user_id'],
            'status' => self::status($g)];
    }

    public function answer(string $token, bool $knows): bool
    {
        $g = $this->byToken($token);
        if ($g === null) {
            return false;
        }
        if ($knows) {
            $this->guardians->confirm($g['user_id'], 'link');
            $this->notifications->notifyTutors('guardian', 'Guardian confirmed: ' . $g['first'], 'They know the student and will support them.', '/studio/guardians');
        } else {
            $this->guardians->decline($g['user_id']);
            $this->notifications->notifyTutors('guardian', 'Check needed: a guardian does not know ' . $g['first'], 'Please check the contact details with the student.', '/studio/students/' . $g['user_id']);
        }

        return true;
    }

    /* ---------- The tutor ---------- */

    public function confirmByCall(int $userId, int $tutorId): void
    {
        $this->guardians->confirm($userId, 'call');
        $this->contacts->logContact($userId, 'phone', '[Guardian] Confirmed on a call that they know the student and will support them', $tutorId);
    }

    /** Records that a message went out (the tutor sends it from their own phone). */
    public function logSent(int $userId, string $what, string $channel, int $tutorId): void
    {
        $label = $what === 'monthly' ? 'Monthly update sent (' . date('F Y') . ')' : 'Admission message sent';
        $this->contacts->logContact($userId, in_array($channel, ['whatsapp', 'email', 'phone'], true) ? $channel : 'whatsapp', '[Guardian] ' . $label, $tutorId);
    }

    /**
     * Updates guardian contact details. Changing a phone number clears a "does not know the child" answer.
     * @param array<string, string> $in guardian_name, guardian_relationship, guardian_phone, guardian_whatsapp, guardian_email, guardian_language
     * @return array<string, string> errors
     */
    public function update(int $userId, array $in): array
    {
        $errors = [];
        $name = trim($in['guardian_name'] ?? '');
        if (mb_strlen($name) < 3) {
            $errors['guardian_name'] = 'Give the guardian’s name.';
        }
        $phone = PhoneNumber::normalize($in['guardian_phone'] ?? '');
        if ($phone === null) {
            $errors['guardian_phone'] = 'Use a Malawian phone number.';
        }
        $wa = trim($in['guardian_whatsapp'] ?? '');
        $waN = $wa === '' ? null : PhoneNumber::normalize($wa);
        if ($wa !== '' && $waN === null) {
            $errors['guardian_whatsapp'] = 'That WhatsApp number does not look right.';
        }
        $email = trim($in['guardian_email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['guardian_email'] = 'That email address does not look right.';
        }
        $lang = $in['guardian_language'] ?? 'en';
        if (!isset(GuardianMessages::LANGUAGES[$lang])) {
            $errors['guardian_language'] = 'Choose Chichewa or English.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $this->guardians->update($userId, [
            'guardian_name' => mb_substr($name, 0, 150),
            'guardian_relationship' => mb_substr(trim($in['guardian_relationship'] ?? ''), 0, 40) ?: null,
            'guardian_phone' => $phone,
            'guardian_whatsapp' => $waN === $phone ? null : $waN,
            'guardian_email' => $email === '' ? null : $email,
            'guardian_language' => $lang,
        ]);

        return [];
    }
}
