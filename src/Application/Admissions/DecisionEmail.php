<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/** The email an applicant gets after a decision (when they gave an email address). Pure. */
final class DecisionEmail
{
    /**
     * @param array<string, mixed> $app
     * @return array{subject: string, text: string}|null
     */
    public static function build(array $app, string $status, string $siteUrl, ?string $letterCode): ?array
    {
        $first = explode(' ', trim((string) $app['full_name']))[0];
        $site = rtrim($siteUrl, '/');
        $letter = $letterCode !== null && $site !== '' ? "\n\nYour letter: " . $site . '/letters/' . $letterCode : '';
        $status_link = $site !== '' ? "\n\nCheck your application at any time: " . $site . '/apply/status (reference ' . $app['reference'] . ').' : '';

        return match ($status) {
            'admitted' => ['subject' => 'Welcome to SMART Circle, ' . $first . '!', 'text' => "Dear {$first},\n\nCongratulations! You have been offered a place in SMART Circle, Form {$app['form']}."
                . "\n\nNext: the tutor will give you your setup code (by WhatsApp or in person). Then open the website, choose \"Set up my account\", and enter your phone number and the code." . $letter . $status_link],
            'waitlisted' => ['subject' => 'Your SMART Circle application: waiting list', 'text' => "Dear {$first},\n\nThank you for applying. The seats for your form are full for now, so you are on the waiting list. If a seat opens, you will be contacted on the phone number in your application."
                . ($site !== '' ? "\n\nMeanwhile, the free library is open to everyone: " . $site . '/library' : '') . $letter . $status_link],
            'declined' => ['subject' => 'Your SMART Circle application', 'text' => "Dear {$first},\n\nThank you for applying. This time we are not able to offer you a seat. Please don't be discouraged: you are welcome to apply again in the next intake."
                . ($site !== '' ? "\n\nThe free library is open to everyone: " . $site . '/library' : '') . $letter . $status_link],
            default => null,
        };
    }
}
