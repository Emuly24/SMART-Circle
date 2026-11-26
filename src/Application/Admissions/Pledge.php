<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/**
 * The SMART Circle Pledge. SMART Circle is free of money; students pay with discipline.
 * Signed at sign-up (and, once the academic calendar exists, renewed each term).
 * Change the wording only by adding a new VERSION, so every signed pledge keeps its own text.
 */
final class Pledge
{
    public const VERSION = '2026-1';

    /** @var array<string, array{0: string, 1: string}> key => [vow, explanation] */
    public const VOWS = [
        'serious' => ['I will take SMART Circle seriously.', 'Lessons, notes, exercises and exams all deserve my full attention.'],
        'hard_work' => ['I will work extra hard.', 'At school and here, I will give more than the minimum.'],
        'respect_teachers' => ['I will respect my teachers and my school.', 'SMART Circle supports them. It never replaces them, and I will never speak against them.'],
        'respect_students' => ['I will respect my fellow students.', 'I will be kind and honest, never copy, and never share my account.'],
        'ask' => ['I will ask questions.', 'When I don’t understand, I will say so, and say exactly where I get lost.'],
        'challenge' => ['I will challenge the tutor constructively.', 'If I think something is wrong, I will say so politely and give my reasons.'],
        'honest' => ['I will be honest.', 'About what my school has taught, about my own work, and about my results.'],
        'maneb' => ['I will share my MANEB results when they come out, good or disappointing.', 'So we can plan my next step together. Sharing is my choice, and no one is ever punished for their results.'],
        'safe' => ['I will keep myself safe.', 'I will not share private details, and I will tell the tutor or my guardian if anything worries me.'],
        'discipline_fee' => ['I understand that my place is paid for with discipline.', 'If I stop taking part for a long time, my seat may go to a student on the waiting list.'],
    ];

    /**
     * @param array<string, mixed> $in vows (list of keys), signed_name, guardian
     * @return array<string, string> errors
     */
    public static function validate(array $in, string $fullName): array
    {
        $errors = [];
        $ticked = is_array($in['vows'] ?? null) ? array_map('strval', $in['vows']) : [];
        if (array_diff(array_keys(self::VOWS), $ticked) !== []) {
            $errors['vows'] = 'Please read and tick every promise. Each one matters.';
        }
        if (self::normalise((string) ($in['signed_name'] ?? '')) !== self::normalise($fullName)) {
            $errors['signed_name'] = 'Type your full name exactly as it is on your account: ' . $fullName . '.';
        }
        if (($in['guardian'] ?? '') !== '1') {
            $errors['guardian'] = 'Your parent or guardian must know about your pledge.';
        }

        return $errors;
    }

    private static function normalise(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }
}
