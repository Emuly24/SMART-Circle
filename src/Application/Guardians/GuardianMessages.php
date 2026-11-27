<?php

declare(strict_types=1);

namespace SmartCircle\Application\Guardians;

use SmartCircle\Application\Templates\TemplateService;

/**
 * Ready-made messages to guardians, in Chichewa (ny) or English (en), filled in with the student's details.
 * The Chichewa wording is a draft to be checked by a fluent speaker before wide use.
 */
final class GuardianMessages
{
    public const LANGUAGES = ['ny' => 'Chichewa', 'en' => 'English'];

    private const MONTHS_NY = ['Januwale', 'Febuluwale', 'Malichi', 'Epulo', 'Meyi', 'Juni', 'Julayi', 'Ogasiti', 'Seputembala', 'Okutobala', 'Novembala', 'Disembala'];

    /**
     * The admission message: the child has joined, what support they need, and the confirmation link.
     * $template is the admin's own wording (TemplateService); null uses the default.
     * @param array<string, string> $d guardian, child, child_first, form, school, link, tutor, tutor_phone
     */
    public static function admission(string $lang, array $d, ?string $template = null): string
    {
        $lang = $lang === 'ny' ? 'ny' : 'en';
        $call = $d['tutor_phone'] !== '' ? $d['tutor_phone'] : '';
        $vars = $d + [
            'school_part' => $d['school'] !== '' ? ', ' . $d['school'] : '',
            'call_part' => $call === '' ? '' : ($lang === 'ny' ? 'Mukhozanso kuyankha uthengawu kapena kundiimbira pa ' . $call . '. ' : 'You can also reply to this message or call me on ' . $call . '. '),
        ];

        return TemplateService::fill($template ?? TemplateService::DEFAULTS['guardian_admission_' . $lang]['body'], $vars);
    }

    public static function admissionSubject(string $lang, string $childFirst, ?string $template = null): string
    {
        $lang = $lang === 'ny' ? 'ny' : 'en';

        return TemplateService::fill($template ?? (string) TemplateService::DEFAULTS['guardian_admission_' . $lang]['subject'], ['child_first' => $childFirst]);
    }

    /**
     * Points for the tutor to cover on a call, in the guardian's language.
     * @return list<string>
     */
    public static function callScript(string $lang, string $childFirst): array
    {
        if ($lang === 'ny') {
            return [
                'Dzidziwitseni: ndine mphunzitsi wa SMART Circle.',
                $childFirst . ' walandiridwa mu SMART Circle, pulogalamu yaulere ya maphunziro.',
                'Funsani ngati akumudziwa ' . $childFirst . ' ndipo ndi ubale wotani.',
                'Thandizo lofunika: foni kapena tabuleti, data pang\'ono, nthawi yabata yowerenga kunyumba.',
                'Ndi zaulere: palibe malipiro kapena chisomo chilichonse chomwe chingafunsidwe.',
                'Funsani ngati ali ndi mafunso, ndipo athokozeni.',
            ];
        }

        return [
            'Introduce yourself: the SMART Circle tutor.',
            $childFirst . ' has been admitted to SMART Circle, a free study programme.',
            'Ask whether they know ' . $childFirst . ' and how they are related.',
            'Support needed: a phone or tablet, a little data, quiet study time at home.',
            'It is free: no payment or favour of any kind will ever be asked.',
            'Ask if they have questions, and thank them.',
        ];
    }

    /**
     * The monthly update. Pure. $template is the admin's own wording; null uses the default.
     * @param array<string, string> $d guardian, child_first, tutor
     */
    public static function monthly(string $lang, array $d, int $lessons, int $work, int $monthNumber, ?string $template = null): string
    {
        $lang = $lang === 'ny' ? 'ny' : 'en';
        $n = $lessons + $work;
        $month = max(1, min(12, $monthNumber));
        if ($lang === 'ny') {
            $note = $n === 0
                ? 'Sitinamuone kwambiri ' . $d['child_first'] . ' mwezi uno; kumulimbikitsa pang\'ono kungathandize kwambiri.'
                : ($n >= 8 ? $d['child_first'] . ' akulimbikira kwambiri. Chonde muuzeni kuti tikunyadira naye.'
                    : 'Chonde pitirizani kumulimbikitsa ' . $d['child_first'] . ' kupeza nthawi yowerenga sabata iliyonse.');
            $monthName = self::MONTHS_NY[$month - 1];
        } else {
            $note = $n === 0
                ? 'We have not seen ' . $d['child_first'] . ' much this month; a little encouragement from you would help a lot.'
                : ($n >= 8 ? $d['child_first'] . ' is working hard. Please tell them we are proud of them.'
                    : 'Please keep encouraging ' . $d['child_first'] . ' to find study time each week.');
            $monthName = date('F', (int) mktime(0, 0, 0, $month, 1));
        }
        $vars = $d + [
            'month' => $monthName, 'lessons' => (string) $lessons, 'work' => (string) $work, 'note' => $note,
            'lessons_text' => $lessons . ' lesson' . ($lessons === 1 ? '' : 's'),
            'work_text' => $work . ' piece' . ($work === 1 ? '' : 's') . ' of work',
        ];

        return TemplateService::fill($template ?? TemplateService::DEFAULTS['guardian_monthly_' . $lang]['body'], $vars);
    }

    /** Wording for the guardian's confirmation page. @return array<string, string> */
    public static function confirmPage(string $lang, string $childFirst): array
    {
        if ($lang === 'ny') {
            return [
                'title' => 'Tsimikizirani',
                'lead' => $childFirst . ' walandiridwa mu SMART Circle, pulogalamu yaulere yothandiza ophunzira a Fomu 1 mpaka 4. Kodi mukumudziwa, ndipo mudzamuthandiza?',
                'support' => 'Thandizo lofunika: foni kapena tabuleti masiku ena pa sabata, data pang\'ono ya intaneti, ndi nthawi yabata yowerenga kunyumba. Palibe malipiro.',
                'yes' => 'Inde, ndikumudziwa ' . $childFirst . ' ndipo ndidzamuthandiza',
                'no' => 'Sindikumudziwa mwana uyu',
                'thanks_yes' => 'Zikomo kwambiri! Mphunzitsi wadziwitsidwa.',
                'thanks_no' => 'Zikomo potidziwitsa. Mphunzitsi adzaonetsetsa.',
                'switch' => 'Read in English',
            ];
        }

        return [
            'title' => 'Please confirm',
            'lead' => $childFirst . ' has been admitted to SMART Circle, a free programme that helps Form 1 to 4 students. Do you know them, and will you support them?',
            'support' => 'Support needed: a phone or tablet on some days each week, a little internet data, and quiet study time at home. There is nothing to pay.',
            'yes' => 'Yes, I know ' . $childFirst . ' and will support them',
            'no' => 'I do not know this child',
            'thanks_yes' => 'Thank you! The tutor has been told.',
            'thanks_no' => 'Thank you for letting us know. The tutor will check.',
            'switch' => 'Werengani mu Chichewa',
        ];
    }
}
