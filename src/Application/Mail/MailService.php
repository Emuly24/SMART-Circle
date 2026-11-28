<?php

declare(strict_types=1);

namespace SmartCircle\Application\Mail;

use SmartCircle\Data\Mail\MailException;
use SmartCircle\Data\Mail\MailTransport;
use SmartCircle\Data\Repositories\MailRepository;

/**
 * Email through Zoho SMTP. Nothing is sent while a page is being built: messages wait in a queue and go out
 * a few at a time after normal page visits (InfinityFree has no cron), or when the admin presses "Send now".
 * A failed send is retried later (10 min, 1 h, 6 h, 24 h) before it is marked failed.
 */
final class MailService
{
    private const RETRY_MINUTES = [10, 60, 360, 1440];

    public function __construct(
        private readonly MailRepository $mail,
        private readonly ?MailTransport $transport,   // null when no Zoho password is set in secrets.php
        private readonly string $siteUrl,
        private readonly int $batchSize = 10,
    ) {
    }

    public function enabled(): bool
    {
        return $this->transport !== null;
    }

    /**
     * Queues an email. The body is plain text (blank lines make paragraphs; links are made clickable);
     * the branded HTML version is built from it.
     */
    public function queue(string $toEmail, string $toName, string $subject, string $text, string $template, ?string $relatedType = null, ?int $relatedId = null, ?int $createdBy = null): bool
    {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = trim((string) preg_replace('/\s+/', ' ', $subject));
        $text = trim(str_replace("\r\n", "\n", $text)) . "\n\n" . self::footerText($this->siteUrl);
        $this->mail->queue([
            'to_email' => $toEmail, 'to_name' => trim((string) preg_replace('/\s+/', ' ', $toName)), 'subject' => mb_substr($subject, 0, 200),
            'text' => $text, 'html' => self::html($subject, $text), 'template' => $template,
            'related_type' => $relatedType, 'related_id' => $relatedId, 'created_by' => $createdBy,
        ]);

        return true;
    }

    /** Emails a notification to those of $userIds who have an email address and want notifications by email. */
    public function notifyUsers(array $userIds, string $title, ?string $body, ?string $link): int
    {
        $n = 0;
        $url = $link !== null && $link !== '' ? rtrim($this->siteUrl, '/') . $link : rtrim($this->siteUrl, '/') . '/notifications';
        foreach ($this->mail->notifiable($userIds) as $u) {
            $first = explode(' ', trim((string) $u['full_name']))[0];
            $text = "Hello {$first},\n\n" . $title . ($body !== null && $body !== '' ? "\n\n" . $body : '') . "\n\nOpen it: " . $url
                . "\n\nYou get this email because you have notifications by email turned on. You can turn them off in your profile: " . rtrim($this->siteUrl, '/') . '/profile';
            $n += $this->queue((string) $u['email'], (string) $u['full_name'], $title, $text, 'notification', 'user', (int) $u['id']) ? 1 : 0;
        }

        return $n;
    }

    /** Groups the tutor can write to. */
    public const GROUPS = [
        'students' => 'Students',
        'guardians' => 'Parents and guardians',
        'partner' => 'Partner teachers',
        'team' => 'Team tutors',
        'alumni' => 'Alumni',
    ];

    /** How many people in each group can get an email. @return array<string, int> */
    public function audienceCounts(): array
    {
        $out = [];
        foreach (array_keys(self::GROUPS) as $g) {
            $out[$g] = count($this->mail->audience($g));
        }

        return $out;
    }

    /**
     * The tutor's own email to a group (optionally one form, for students and guardians). "{name}" becomes each
     * person's first name. Each person gets their own copy (no one sees the other addresses).
     * @param array<string, mixed> $in
     * @return array{errors: array<string, string>, count: int}
     */
    public function broadcast(array $in, int $createdBy): array
    {
        $group = (string) ($in['group'] ?? '');
        $form = (int) ($in['form'] ?? 0);
        $subject = trim((string) preg_replace('/\s+/', ' ', (string) ($in['subject'] ?? '')));
        $body = trim(str_replace("\r\n", "\n", (string) ($in['body'] ?? '')));
        $errors = [];
        if (!isset(self::GROUPS[$group])) {
            $errors['group'] = 'Choose who the email is for.';
        }
        if ($form < 0 || $form > 4) {
            $errors['form'] = 'Choose a form.';
        }
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
            $errors['subject'] = 'Write a subject (3 to 150 characters).';
        }
        if (mb_strlen($body) < 10 || mb_strlen($body) > 6000) {
            $errors['body'] = 'Write the message (10 to 6,000 characters).';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'count' => 0];
        }
        $people = $this->mail->audience($group, in_array($group, ['students', 'guardians'], true) && $form > 0 ? $form : null);
        if ($people === []) {
            return ['errors' => ['group' => 'No one in that group has an email address with email turned on yet.'], 'count' => 0];
        }
        $n = 0;
        foreach ($people as $p) {
            $first = explode(' ', trim($p['name']))[0] ?? '';
            $text = str_replace('{name}', $first !== '' ? $first : 'there', $body);
            $n += $this->queue($p['email'], $p['name'], $subject, $text, 'broadcast', 'broadcast', null, $createdBy) ? 1 : 0;
        }

        return ['errors' => [], 'count' => $n];
    }

    /**
     * Sends what is due, within a time budget. Called after page visits (lazily) and by "Send now".
     * @return array{sent: int, failed: int, left: int, error: string}
     */
    public function sendDue(int $max = 0, float $seconds = 8.0): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'left' => 0, 'error' => ''];
        if ($this->transport === null) {
            $out['error'] = 'Email is not set up: add the Zoho password to secrets.php.';
            $out['left'] = $this->mail->countDue();
            return $out;
        }
        $start = microtime(true);
        foreach ($this->mail->due($max > 0 ? $max : $this->batchSize) as $m) {
            if (microtime(true) - $start > $seconds) {
                break;
            }
            try {
                $this->transport->send((string) $m['to_email'], (string) $m['to_name'], (string) $m['subject'], (string) $m['text_body'], (string) $m['html_body']);
                $this->mail->markSent((int) $m['id']);
                $out['sent']++;
            } catch (MailException $e) {
                $retry = self::RETRY_MINUTES[(int) $m['attempts']] ?? null;
                $this->mail->markFailed((int) $m['id'], $e->getMessage(), $retry);
                $out['failed']++;
                $out['error'] = $e->getMessage();
                if (str_contains($e->getMessage(), 'Could not reach') || str_contains($e->getMessage(), 'AUTH')) {
                    break; // the server is down or the password is wrong: no point trying the rest now
                }
            }
        }
        $out['left'] = $this->mail->countDue();

        return $out;
    }

    /** After a page has been sent: at most one request every 2 minutes sends a few emails. */
    public function runIfDue(): void
    {
        if ($this->transport === null || $this->mail->countDue() === 0 || !$this->mail->claimJob('mail')) {
            return;
        }
        $r = $this->sendDue(min(5, $this->batchSize), 6.0);
        $this->mail->jobResult('mail', 'Sent ' . $r['sent'] . ', failed ' . $r['failed'] . ($r['error'] !== '' ? ': ' . $r['error'] : ''));
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return ['enabled' => $this->enabled(), 'counts' => $this->mail->counts(), 'recent' => $this->mail->recent(), 'job' => $this->mail->job('mail')];
    }

    public function retryFailed(): int
    {
        return $this->mail->retryFailed();
    }

    /** The branded HTML version of a plain-text email. Pure. Email clients need inline styles. */
    public static function html(string $subject, string $text): string
    {
        $paras = preg_split('/\n{2,}/', trim($text)) ?: [];
        $body = '';
        foreach ($paras as $p) {
            $safe = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safe = (string) preg_replace('#(https?://[^\s<]+[^\s<.,;:!?)])#', '<a href="$1" style="color:#1D4E89;font-weight:600">$1</a>', $safe);
            $body .= '<p style="margin:0 0 14px;line-height:1.6">' . nl2br($safe, false) . '</p>';
        }
        $title = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title></head>'
            . '<body style="margin:0;padding:0;background:#F4F6FA;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;color:#14213D;font-size:16px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F6FA"><tr><td align="center" style="padding:20px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border-radius:14px;overflow:hidden">'
            . '<tr><td style="background:#1D4E89;padding:16px 22px;color:#FFFFFF;font-size:20px;font-weight:800">SMART <span style="font-weight:500">Circle</span>'
            . '<div style="font-size:11px;letter-spacing:.12em;color:#C9D3E3;font-weight:600">UNDERSTAND IT. APPLY IT.</div></td></tr>'
            . '<tr><td style="height:4px;background:#1F9D6B"></td></tr>'
            . '<tr><td style="padding:22px">' . '<h1 style="margin:0 0 16px;font-size:20px;color:#1D4E89">' . $title . '</h1>' . $body . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function footerText(string $siteUrl): string
    {
        return "SMART Circle · Understand it. Apply it.\nFree support for Form 1–4 students in Malawi" . ($siteUrl !== '' ? ' · ' . rtrim($siteUrl, '/') : '') . "\nReplies to this email reach the tutor.";
    }
}
