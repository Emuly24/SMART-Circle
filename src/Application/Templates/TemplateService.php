<?php

declare(strict_types=1);

namespace SmartCircle\Application\Templates;

use SmartCircle\Data\Repositories\TemplateRepository;

/**
 * Ready-made messages the admin can reword. Each has a default here; a saved version replaces it until
 * "Reset to default". {{placeholders}} are filled in when a message is made; required ones (such as the
 * guardian's confirmation link) cannot be removed.
 */
final class TemplateService
{
    /** key => name, channel, subject (null = none), body, placeholders (name => meaning), required */
    public const DEFAULTS = [
        'guardian_admission_en' => [
            'name' => 'Guardian welcome message (English)', 'channel' => 'whatsapp', 'subject' => 'SMART Circle: {{child_first}} has been admitted',
            'body' => 'Hello {{guardian}}, this is {{tutor}} from SMART Circle, a free programme that helps Form 1 to 4 students with Mathematics, Physics, Chemistry, Biology and English grammar. {{child}} (Form {{form}}{{school_part}}) has been admitted. It is completely free. To do well, {{child_first}} will need your support: a phone or tablet to use on some days each week, a little data or airtime for the internet, and quiet time at home to study. Please confirm that you know {{child_first}} and will support them here: {{link}} {{call_part}}Thank you!',
            'placeholders' => ['guardian' => 'Guardian\'s name', 'tutor' => 'Your name', 'child' => 'Student\'s full name', 'child_first' => 'Student\'s first name', 'form' => 'Form', 'school_part' => '", School name" (or nothing)', 'link' => 'Confirmation link', 'call_part' => '"You can also reply ... call me on ..." (or nothing)'],
            'required' => ['link'],
        ],
        'guardian_admission_ny' => [
            'name' => 'Guardian welcome message (Chichewa)', 'channel' => 'whatsapp', 'subject' => 'SMART Circle: {{child_first}} walandiridwa',
            'body' => 'Moni {{guardian}}, ndine {{tutor}} wa SMART Circle, pulogalamu yaulere yothandiza ophunzira a Fomu 1 mpaka 4 pa Masamu, Fizikisi, Kemisitire, Baiolojire ndi Galamala ya Chingerezi. {{child}} (Fomu {{form}}{{school_part}}) walandiridwa mu pulogalamuyi. Ndi zaulere kwathunthu. Kuti achite bwino, {{child_first}} adzafunika thandizo lanu: foni kapena tabuleti yoti agwiritse ntchito masiku ena pa sabata, data kapena ma yunitsi pang\'ono a intaneti, komanso nthawi yabata kunyumba yowerenga. Chonde tsimikizirani kuti mukumudziwa {{child_first}} ndipo mudzamuthandiza podina apa: {{link}} {{call_part}}Zikomo kwambiri!',
            'placeholders' => ['guardian' => 'Dzina la makolo', 'tutor' => 'Dzina lanu', 'child' => 'Dzina lonse la mwana', 'child_first' => 'Dzina loyamba', 'form' => 'Fomu', 'school_part' => '", sukulu" (kapena palibe)', 'link' => 'Ulalo wotsimikizira', 'call_part' => '"Mukhozanso ... pa ..." (kapena palibe)'],
            'required' => ['link'],
        ],
        'guardian_monthly_en' => [
            'name' => 'Guardian monthly update (English)', 'channel' => 'whatsapp', 'subject' => 'SMART Circle: {{child_first}} – monthly update',
            'body' => 'Hello {{guardian}}, a short update from SMART Circle about {{child_first}} for {{month}}: {{lessons_text}} watched and {{work_text}} handed in. {{note}} Thank you for your support at home. {{tutor}}',
            'placeholders' => ['guardian' => 'Guardian\'s name', 'child_first' => 'Student\'s first name', 'month' => 'Month', 'lessons_text' => 'e.g. "3 lessons"', 'work_text' => 'e.g. "2 pieces of work"', 'note' => 'A sentence that depends on how active they were', 'tutor' => 'Your name'],
            'required' => [],
        ],
        'guardian_monthly_ny' => [
            'name' => 'Guardian monthly update (Chichewa)', 'channel' => 'whatsapp', 'subject' => 'SMART Circle: {{child_first}}',
            'body' => 'Moni {{guardian}}, uthenga wachidule wochokera ku SMART Circle wokhudza {{child_first}} mwezi wa {{month}}: waonera maphunziro {{lessons}} ndipo wapereka ntchito {{work}}. {{note}} Zikomo chifukwa cha thandizo lanu kunyumba. {{tutor}}',
            'placeholders' => ['guardian' => 'Dzina la makolo', 'child_first' => 'Dzina loyamba', 'month' => 'Mwezi', 'lessons' => 'Maphunziro (nambala)', 'work' => 'Ntchito (nambala)', 'note' => 'Chiganizo chokhudza khama lake', 'tutor' => 'Dzina lanu'],
            'required' => [],
        ],
        'decision_admitted' => [
            'name' => 'Email to an admitted applicant', 'channel' => 'email', 'subject' => 'Welcome to SMART Circle, {{first}}!',
            'body' => "Dear {{first}},\n\nCongratulations! You have been offered a place in SMART Circle, Form {{form}}.\n\nNext: the tutor will give you your setup code (by WhatsApp or in person). Then open the website, choose \"Set up my account\", and enter your phone number and the code.{{letter_part}}{{status_part}}",
            'placeholders' => ['first' => 'First name', 'form' => 'Form', 'letter_part' => 'Link to their letter', 'status_part' => 'Link to check their application'],
            'required' => [],
        ],
        'decision_waitlisted' => [
            'name' => 'Email to an applicant on the waiting list', 'channel' => 'email', 'subject' => 'Your SMART Circle application: waiting list',
            'body' => "Dear {{first}},\n\nThank you for applying. The seats for your form are full for now, so you are on the waiting list. If a seat opens, you will be contacted on the phone number in your application.{{library_part}}{{letter_part}}{{status_part}}",
            'placeholders' => ['first' => 'First name', 'library_part' => 'Link to the free library', 'letter_part' => 'Link to their letter', 'status_part' => 'Link to check their application'],
            'required' => [],
        ],
        'decision_declined' => [
            'name' => 'Email to an applicant not admitted', 'channel' => 'email', 'subject' => 'Your SMART Circle application',
            'body' => "Dear {{first}},\n\nThank you for applying. This time we are not able to offer you a seat. Please don't be discouraged: you are welcome to apply again in the next intake.{{library_part}}{{letter_part}}{{status_part}}",
            'placeholders' => ['first' => 'First name', 'library_part' => 'Link to the free library', 'letter_part' => 'Link to their letter', 'status_part' => 'Link to check their application'],
            'required' => [],
        ],
        'student_nudge' => [
            'name' => 'Friendly nudge to a quiet student', 'channel' => 'notification', 'subject' => 'We miss you, {{student}}!',
            'body' => 'There are lessons and notes waiting for you at SMART Circle. Even 20 minutes this week will help. If something is making it hard (no phone, no data, a busy time at school), write to the tutor in Messages and we will work it out together.',
            'placeholders' => ['student' => 'Student\'s first name'],
            'required' => [],
        ],
    ];

    public function __construct(private readonly TemplateRepository $repo)
    {
    }

    /** @return array{subject: string, body: string} filled in with $vars */
    public function render(string $key, array $vars): array
    {
        $t = $this->current($key);

        return ['subject' => self::fill((string) $t['subject'], $vars), 'body' => self::fill((string) $t['body'], $vars)];
    }

    /** The wording in use: the admin's saved version, else the default. @return array<string, mixed> */
    public function current(string $key): array
    {
        $d = self::DEFAULTS[$key] ?? throw new \InvalidArgumentException('Unknown template ' . $key);
        $saved = $this->repo->find($key);

        return array_merge($d, [
            'key' => $key,
            'custom' => $saved !== null,
            'subject' => $saved !== null && $d['subject'] !== null ? (string) $saved['subject'] : $d['subject'],
            'body' => $saved !== null ? (string) $saved['body'] : $d['body'],
            'updated_at' => $saved['updated_at'] ?? null,
        ]);
    }

    /** @return array<string, array<string, mixed>> every template with its wording in use */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = $this->current($key);
        }

        return $out;
    }

    /** @return array<string, string> errors */
    public function save(string $key, string $subject, string $body, int $by): array
    {
        $d = self::DEFAULTS[$key] ?? throw new \InvalidArgumentException('Unknown template ' . $key);
        $errors = self::validate($d, $subject, $body);
        if ($errors === []) {
            $this->repo->save($key, $d['name'], $d['channel'], $d['subject'] !== null ? trim($subject) : null, trim(str_replace("\r\n", "\n", $body)), $by);
        }

        return $errors;
    }

    public function reset(string $key): void
    {
        if (isset(self::DEFAULTS[$key])) {
            $this->repo->delete($key);
        }
    }

    /**
     * Checks wording before it is saved. Pure.
     * @param array<string, mixed> $d
     * @return array<string, string>
     */
    public static function validate(array $d, string $subject, string $body): array
    {
        $errors = [];
        $known = array_keys($d['placeholders']);
        foreach (['subject' => $subject, 'body' => $body] as $field => $text) {
            if ($field === 'subject' && $d['subject'] === null) {
                continue;
            }
            if (trim($text) === '') {
                $errors[$field] = 'This cannot be empty.';
                continue;
            }
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $m);
            $unknown = array_diff($m[1], $known);
            if ($unknown !== []) {
                $errors[$field] = 'Unknown placeholder: {{' . implode('}}, {{', array_unique($unknown)) . '}}. Use only the ones listed.';
            }
        }
        if (mb_strlen($subject) > 200) {
            $errors['subject'] = 'Keep the subject under 200 characters.';
        }
        if (mb_strlen($body) > 5000) {
            $errors['body'] = 'Keep the message under 5000 characters.';
        }
        foreach ($d['required'] as $req) {
            if (!isset($errors['body']) && !preg_match('/\{\{\s*' . $req . '\s*\}\}/', $body)) {
                $errors['body'] = 'The message must keep {{' . $req . '}}.';
            }
        }

        return $errors;
    }

    /** Fills in {{placeholders}}. Pure. */
    public static function fill(string $text, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static fn (array $m): string => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', $text);
    }
}
