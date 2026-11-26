<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/**
 * "Your goals and habits" on the application. Mostly choices (quick on a phone), plus two short
 * answers. Scenario answers carry a quiet signal for the tutor: growth / neutral / concern.
 * They never decide admission on their own; they help the tutor ask the right follow-up.
 */
final class Questionnaire
{
    /** @var array<string, array{label: string, options: array<string, array{0: string, 1: string}>}> key => question; option => [text, signal] */
    public const CHOICES = [
        'schooling_reason' => [
            'label' => 'Why do you go to school?',
            'options' => [
                'understand' => ['To understand how the world around me works', 'growth'],
                'develop' => ['To develop myself and my mind', 'growth'],
                'help_others' => ['To be able to help my family and community', 'growth'],
                'job' => ['To get a good job one day', 'neutral'],
                'parents' => ['Because my parents want me to', 'concern'],
                'other' => ['Another reason', 'neutral'],
            ],
        ],
        'career_interest' => [
            'label' => 'Which career interests you most right now?',
            'options' => [
                'health' => ['Medicine, nursing or health', 'neutral'],
                'engineering' => ['Engineering or mining', 'neutral'],
                'agriculture' => ['Agriculture or environment', 'neutral'],
                'teaching' => ['Teaching', 'neutral'],
                'business' => ['Business, accounting or banking', 'neutral'],
                'law' => ['Law or public service', 'neutral'],
                'ict' => ['Computers and ICT', 'neutral'],
                'science' => ['Science research or laboratory work', 'neutral'],
                'arts' => ['Arts, media or communication', 'neutral'],
                'trades' => ['A skilled trade (technical college)', 'neutral'],
                'own_business' => ['Starting my own business', 'neutral'],
                'not_sure' => ['Not sure yet', 'neutral'],
                'other' => ['Something else', 'neutral'],
            ],
        ],
        'heard_from' => [
            'label' => 'How did you hear about SMART Circle?',
            'options' => [
                'teacher' => ['A teacher at my school', 'neutral'],
                'friend' => ['A friend or classmate', 'neutral'],
                'student' => ['A SMART Circle student', 'neutral'],
                'family' => ['A family member', 'neutral'],
                'social' => ['Facebook or WhatsApp', 'neutral'],
                'poster' => ['A poster or notice', 'neutral'],
                'other' => ['Somewhere else', 'neutral'],
            ],
        ],
        'study_hours' => [
            'label' => 'On a normal school day, how long do you study outside class?',
            'options' => [
                'under_30' => ['Less than 30 minutes', 'concern'],
                '30_60' => ['30 minutes to 1 hour', 'neutral'],
                '1_2' => ['1 to 2 hours', 'growth'],
                'over_2' => ['More than 2 hours', 'growth'],
            ],
        ],
        'seriousness' => [
            'label' => 'How serious are you about your school work?',
            'options' => [
                '5' => ['Very serious: school comes first for me', 'growth'],
                '4' => ['Serious, most of the time', 'growth'],
                '3' => ['Sometimes serious, sometimes not', 'neutral'],
                '2' => ['Not very serious yet, but I want to change', 'neutral'],
            ],
        ],
        'setback' => [
            'label' => 'You studied hard but failed a test. What do you do first?',
            'options' => [
                'review' => ['Find out what I got wrong and practise it', 'growth'],
                'ask' => ['Ask my teacher or a classmate to explain', 'growth'],
                'leave' => ['Feel bad and try to forget it', 'concern'],
                'give_up' => ['Decide that subject is not for me', 'concern'],
            ],
        ],
        'discipline' => [
            'label' => 'Friends invite you out the evening before a test. What do you do?',
            'options' => [
                'study_first' => ['Study first, then join them if there is time', 'growth'],
                'stay' => ['Explain that I need to study, and stay', 'growth'],
                'go_then_study' => ['Go, and study when I come back', 'neutral'],
                'skip' => ['Go and skip studying this time', 'concern'],
            ],
        ],
        'confidence' => [
            'label' => 'When you don’t understand something in class, what do you usually do?',
            'options' => [
                'ask_class' => ['Ask the teacher during the lesson', 'growth'],
                'ask_after' => ['Ask the teacher after the lesson', 'growth'],
                'ask_friend' => ['Ask a friend', 'neutral'],
                'quiet' => ['Keep quiet and hope to understand later', 'concern'],
            ],
        ],
    ];

    /** @var array<string, array{0: string, 1: string}> key => [label, placeholder] */
    public const TEXTS = [
        'benefit' => ['How will SMART Circle help you, at school and beyond?', 'e.g. I will understand physics properly and see how it is used in farming and energy at home.'],
        'helpful_teacher' => ['Tell us about a teacher who has helped you. What did they do?', 'e.g. My maths teacher stayed after class to explain fractions until I understood.'],
    ];

    /**
     * @param array<string, mixed> $in
     * @return array{0: array<string, string>, 1: array<string, string>} [errors, clean answers]
     */
    public static function validate(array $in): array
    {
        $errors = [];
        $clean = [];
        foreach (self::CHOICES as $key => $q) {
            $v = (string) ($in[$key] ?? '');
            if (!isset($q['options'][$v])) {
                $errors[$key] = 'Please choose an answer.';
                continue;
            }
            $clean[$key] = $v;
        }
        foreach (['schooling_reason', 'career_interest'] as $key) {
            if (($clean[$key] ?? '') === 'other') {
                $other = trim((string) ($in[$key . '_other'] ?? ''));
                if (mb_strlen($other) < 3) {
                    $errors[$key] = 'Please tell us in a few words.';
                }
                $clean[$key . '_other'] = mb_substr($other, 0, 150);
            }
        }
        foreach (self::TEXTS as $key => [$label]) {
            $v = trim(str_replace("\r\n", "\n", (string) ($in[$key] ?? '')));
            if (mb_strlen($v) < 20) {
                $errors[$key] = 'Please write at least a sentence or two.';
            } elseif (mb_strlen($v) > 1000) {
                $errors[$key] = 'Please keep this under 1,000 characters.';
            }
            $clean[$key] = mb_substr($v, 0, 1000);
        }

        return [$errors, $clean];
    }

    /**
     * For the tutor's review page: each answer in words, with its signal, and the totals.
     * @param array<string, string> $answers
     * @return array{rows: list<array{label: string, answer: string, signal: string}>, counts: array<string, int>}
     */
    public static function summary(array $answers): array
    {
        $rows = [];
        $counts = ['growth' => 0, 'neutral' => 0, 'concern' => 0];
        foreach (self::CHOICES as $key => $q) {
            if (!isset($answers[$key], $q['options'][$answers[$key]])) {
                continue;
            }
            [$text, $signal] = $q['options'][$answers[$key]];
            if (($answers[$key . '_other'] ?? '') !== '') {
                $text .= ': ' . $answers[$key . '_other'];
            }
            $rows[] = ['label' => $q['label'], 'answer' => $text, 'signal' => $signal];
            $counts[$signal]++;
        }
        foreach (self::TEXTS as $key => [$label]) {
            if (($answers[$key] ?? '') !== '') {
                $rows[] = ['label' => $label, 'answer' => $answers[$key], 'signal' => 'text'];
            }
        }

        return ['rows' => $rows, 'counts' => $counts];
    }
}
