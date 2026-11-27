<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

use SmartCircle\Support\Malawi;
use SmartCircle\Support\PhoneNumber;
use SmartCircle\Support\Subjects;

/**
 * Validates and cleans an application (pure: no database), so it is easy to test.
 */
final class ApplicationForm
{
    public const GUARDIAN_LANGUAGES = ['ny' => 'Chichewa', 'en' => 'English'];

    public const DEVICES = [
        'own_phone' => 'My own smartphone',
        'shared_phone' => 'A family member\'s smartphone',
        'computer' => 'A computer or tablet',
        'none' => 'No device yet',
    ];

    public const DATA_ACCESS = [
        'daily' => 'Most days',
        'weekly' => 'About once a week',
        'rarely' => 'Rarely',
    ];

    public const RELATIONSHIPS = ['Parent', 'Guardian', 'Grandparent', 'Older sibling', 'Other relative'];

    /**
     * @param array<string, mixed> $in raw form input
     * @return array{0: array<string, string>, 1: array<string, mixed>} [errors, clean data]
     */
    public static function validate(array $in): array
    {
        $str = static fn (string $k): string => trim((string) ($in[$k] ?? ''));
        $errors = [];
        $data = [];

        $data['full_name'] = preg_replace('/\s+/', ' ', $str('full_name')) ?? '';
        if (mb_strlen($data['full_name']) < 3 || mb_strlen($data['full_name']) > 150 || !str_contains($data['full_name'], ' ')) {
            $errors['full_name'] = 'Enter your first name and surname.';
        }

        $data['gender'] = $str('gender');
        if (!in_array($data['gender'], ['female', 'male'], true)) {
            $errors['gender'] = 'Choose girl or boy.';
        }

        $data['form'] = (int) $str('form');
        if ($data['form'] < 1 || $data['form'] > 4) {
            $errors['form'] = 'Choose your form.';
        }

        $data['school_name'] = preg_replace('/\s+/', ' ', $str('school_name')) ?? '';
        if (mb_strlen($data['school_name']) < 3 || mb_strlen($data['school_name']) > 150) {
            $errors['school_name'] = 'Enter the name of your school.';
        }

        $data['district'] = $str('district');
        if (!in_array($data['district'], Malawi::DISTRICTS, true)) {
            $errors['district'] = 'Choose your school\'s district.';
        }

        $phone = PhoneNumber::normalize($str('phone'));
        $data['phone'] = $phone ?? '';
        if ($phone === null) {
            $errors['phone'] = 'Enter a Malawian phone number, e.g. 0999 123 456. You will log in with it.';
        }

        $email = $str('email');
        $data['email'] = $email === '' ? null : $email;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'This email address does not look right (or leave it empty).';
        }

        $data['subjects_taken'] = Subjects::clean($in['subjects_taken'] ?? []);
        if ($data['subjects_taken'] === []) {
            $errors['subjects_taken'] = 'Tick the subjects you take at school.';
        }

        $data['track'] = Subjects::track($data['form'] ?: 1, $data['subjects_taken']);
        $allowed = Subjects::forTrack($data['track']);
        $help = Subjects::clean($in['help_subjects'] ?? []);
        $data['help_subjects'] = array_values(array_intersect($help, $allowed, $data['subjects_taken']));
        if ($data['help_subjects'] === []) {
            $errors['help_subjects'] = $data['track'] === 'humanities'
                ? 'Choose at least one: Mathematics, Biology or English grammar (subjects you take).'
                : 'Choose at least one subject you take and want help with.';
        }

        $data['struggling_topics'] = mb_substr($str('struggling_topics'), 0, 1000) ?: null;

        $data['motivation'] = $str('motivation');
        $len = mb_strlen($data['motivation']);
        if ($len < 30) {
            $errors['motivation'] = 'Tell us a little more (at least a couple of sentences).';
        } elseif ($len > 1500) {
            $errors['motivation'] = 'Please keep this under 1,500 characters.';
        }

        $data['device'] = $str('device');
        if (!isset(self::DEVICES[$data['device']])) {
            $errors['device'] = 'Choose how you will watch lessons.';
        }
        $data['data_access'] = $str('data_access');
        if (!isset(self::DATA_ACCESS[$data['data_access']])) {
            $errors['data_access'] = 'Choose how often you have internet data.';
        }

        $data['guardian_name'] = preg_replace('/\s+/', ' ', $str('guardian_name')) ?? '';
        if (mb_strlen($data['guardian_name']) < 3 || mb_strlen($data['guardian_name']) > 150) {
            $errors['guardian_name'] = 'Enter your parent or guardian\'s full name.';
        }
        $guardianPhone = PhoneNumber::normalize($str('guardian_phone'));
        $data['guardian_phone'] = $guardianPhone ?? '';
        if ($guardianPhone === null) {
            $errors['guardian_phone'] = 'Enter your parent or guardian\'s phone number.';
        }
        $data['guardian_relationship'] = $str('guardian_relationship');
        if (!in_array($data['guardian_relationship'], self::RELATIONSHIPS, true)) {
            $errors['guardian_relationship'] = 'Choose how they are related to you.';
        }
        // The guardian does not need to be present: SMART Circle contacts them after admission.
        $wa = trim($str('guardian_whatsapp'));
        $data['guardian_whatsapp'] = null;
        if ($wa !== '' && ($in['guardian_whatsapp_same'] ?? '') !== '1') {
            $data['guardian_whatsapp'] = PhoneNumber::normalize($wa);
            if ($data['guardian_whatsapp'] === null) {
                $errors['guardian_whatsapp'] = 'That WhatsApp number does not look right.';
            } elseif ($data['guardian_whatsapp'] === $guardianPhone) {
                $data['guardian_whatsapp'] = null;
            }
        }
        $data['guardian_email'] = trim($str('guardian_email')) ?: null;
        if ($data['guardian_email'] !== null && filter_var($data['guardian_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['guardian_email'] = 'That email address does not look right.';
        }
        $data['guardian_language'] = $str('guardian_language');
        if (!isset(self::GUARDIAN_LANGUAGES[$data['guardian_language']])) {
            $errors['guardian_language'] = 'Choose the language your parent or guardian understands best.';
        }
        if (($in['guardian_consent'] ?? '') !== '1') {
            $errors['guardian_consent'] = 'Please confirm the contact details are correct and that we may contact your parent or guardian.';
        }
        [$qErrors, $data['answers']] = Questionnaire::validate($in);
        $errors += $qErrors;

        if (($in['truthful'] ?? '') !== '1') {
            $errors['truthful'] = 'Please confirm that your answers are true.';
        }

        return [$errors, $data];
    }
}
