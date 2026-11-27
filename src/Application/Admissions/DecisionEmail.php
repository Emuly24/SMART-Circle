<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Application\Templates\TemplateService;

/** The email an applicant gets after a decision (when they gave an email address). */
final class DecisionEmail
{
    /**
     * Pure when $templates is null (default wording).
     * @param array<string, mixed> $app
     * @return array{subject: string, text: string}|null
     */
    public static function build(array $app, string $status, string $siteUrl, ?string $letterCode, ?TemplateService $templates = null): ?array
    {
        if (!in_array($status, ['admitted', 'waitlisted', 'declined'], true)) {
            return null;
        }
        $site = rtrim($siteUrl, '/');
        $vars = [
            'first' => explode(' ', trim((string) $app['full_name']))[0],
            'form' => (string) $app['form'],
            'letter_part' => $letterCode !== null && $site !== '' ? "\n\nYour letter: " . $site . '/letters/' . $letterCode : '',
            'status_part' => $site !== '' ? "\n\nCheck your application at any time: " . $site . '/apply/status (reference ' . $app['reference'] . ').' : '',
            'library_part' => $site !== '' ? "\n\n" . ($status === 'declined' ? 'The free library is open to everyone: ' : 'Meanwhile, the free library is open to everyone: ') . $site . '/library' : '',
        ];
        if ($templates !== null) {
            $t = $templates->render('decision_' . $status, $vars);
        } else {
            $d = TemplateService::DEFAULTS['decision_' . $status];
            $t = ['subject' => TemplateService::fill((string) $d['subject'], $vars), 'body' => TemplateService::fill($d['body'], $vars)];
        }

        return ['subject' => $t['subject'], 'text' => $t['body']];
    }
}
