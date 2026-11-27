<?php

declare(strict_types=1);

namespace SmartCircle\Application\Guardians;

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
     * @param array<string, string> $d guardian, child, child_first, form, school, link, tutor, tutor_phone
     */
    public static function admission(string $lang, array $d): string
    {
        $school = $d['school'] !== '' ? ', ' . $d['school'] : '';
        $call = $d['tutor_phone'] !== '' ? $d['tutor_phone'] : '';
        if ($lang === 'ny') {
            return 'Moni ' . $d['guardian'] . ', ndine ' . $d['tutor'] . ' wa SMART Circle, pulogalamu yaulere yothandiza ophunzira a Fomu 1 mpaka 4 pa Masamu, Fizikisi, Kemisitire, Baiolojire ndi Galamala ya Chingerezi. '
                . $d['child'] . ' (Fomu ' . $d['form'] . $school . ') walandiridwa mu pulogalamuyi. Ndi zaulere kwathunthu. '
                . 'Kuti achite bwino, ' . $d['child_first'] . ' adzafunika thandizo lanu: foni kapena tabuleti yoti agwiritse ntchito masiku ena pa sabata, data kapena ma yunitsi pang\'ono a intaneti, komanso nthawi yabata kunyumba yowerenga. '
                . 'Chonde tsimikizirani kuti mukumudziwa ' . $d['child_first'] . ' ndipo mudzamuthandiza podina apa: ' . $d['link'] . ' '
                . ($call !== '' ? 'Mukhozanso kuyankha uthengawu kapena kundiimbira pa ' . $call . '. ' : '') . 'Zikomo kwambiri!';
        }

        return 'Hello ' . $d['guardian'] . ', this is ' . $d['tutor'] . ' from SMART Circle, a free programme that helps Form 1 to 4 students with Mathematics, Physics, Chemistry, Biology and English grammar. '
            . $d['child'] . ' (Form ' . $d['form'] . $school . ') has been admitted. It is completely free. '
            . 'To do well, ' . $d['child_first'] . ' will need your support: a phone or tablet to use on some days each week, a little data or airtime for the internet, and quiet time at home to study. '
            . 'Please confirm that you know ' . $d['child_first'] . ' and will support them here: ' . $d['link'] . ' '
            . ($call !== '' ? 'You can also reply to this message or call me on ' . $call . '. ' : '') . 'Thank you!';
    }

    public static function admissionSubject(string $lang, string $childFirst): string
    {
        return $lang === 'ny' ? 'SMART Circle: ' . $childFirst . ' walandiridwa' : 'SMART Circle: ' . $childFirst . ' has been admitted';
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
     * The monthly update. Pure.
     * @param array<string, string> $d guardian, child_first, tutor
     */
    public static function monthly(string $lang, array $d, int $lessons, int $work, int $monthNumber): string
    {
        $n = $lessons + $work;
        if ($lang === 'ny') {
            $note = $n === 0
                ? 'Sitinamuone kwambiri ' . $d['child_first'] . ' mwezi uno; kumulimbikitsa pang\'ono kungathandize kwambiri.'
                : ($n >= 8 ? $d['child_first'] . ' akulimbikira kwambiri. Chonde muuzeni kuti tikunyadira naye.'
                    : 'Chonde pitirizani kumulimbikitsa ' . $d['child_first'] . ' kupeza nthawi yowerenga sabata iliyonse.');

            return 'Moni ' . $d['guardian'] . ', uthenga wachidule wochokera ku SMART Circle wokhudza ' . $d['child_first']
                . ' mwezi wa ' . self::MONTHS_NY[max(1, min(12, $monthNumber)) - 1] . ': waonera maphunziro ' . $lessons . ' ndipo wapereka ntchito ' . $work . '. '
                . $note . ' Zikomo chifukwa cha thandizo lanu kunyumba. ' . $d['tutor'];
        }
        $note = $n === 0
            ? 'We have not seen ' . $d['child_first'] . ' much this month; a little encouragement from you would help a lot.'
            : ($n >= 8 ? $d['child_first'] . ' is working hard. Please tell them we are proud of them.'
                : 'Please keep encouraging ' . $d['child_first'] . ' to find study time each week.');

        return 'Hello ' . $d['guardian'] . ', a short update from SMART Circle about ' . $d['child_first'] . ' for '
            . date('F', (int) mktime(0, 0, 0, max(1, min(12, $monthNumber)), 1)) . ': ' . $lessons . ' lesson' . ($lessons === 1 ? '' : 's') . ' watched and '
            . $work . ' piece' . ($work === 1 ? '' : 's') . ' of work handed in. ' . $note . ' Thank you for your support at home. ' . $d['tutor'];
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
