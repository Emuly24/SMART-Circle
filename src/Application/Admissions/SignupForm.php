<?php

declare(strict_types=1);

namespace SmartCircle\Application\Admissions;

/** Sign-up details collected after admission: how the student attends school, and their routine. */
final class SignupForm
{
    public const STUDY_MODES = [
        'day' => 'Day scholar (I go to school from home)',
        'boarder' => 'Full boarder (I live at the school)',
        'self_boarder' => 'Self-boarder (I rent a room near the school)',
        'evening' => 'Evening classes',
    ];

    public const TRAVEL = [
        'under_15' => 'Less than 15 minutes',
        '15_30' => '15 to 30 minutes',
        '30_60' => '30 minutes to 1 hour',
        'over_60' => 'More than 1 hour',
    ];

    public const HOME_STUDY = [
        'morning' => 'Early morning',
        'afternoon' => 'Afternoon after classes',
        'evening' => 'Evening',
        'weekends' => 'Mostly weekends',
    ];

    /**
     * @param array<string, mixed> $in study_mode, school_area, travel_time, home_study
     * @return array{0: array<string, string>, 1: array<string, ?string>}
     */
    public static function validate(array $in): array
    {
        $errors = [];
        $mode = (string) ($in['study_mode'] ?? '');
        $area = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['school_area'] ?? '')));
        $travel = (string) ($in['travel_time'] ?? '');
        $home = (string) ($in['home_study'] ?? '');

        if (!isset(self::STUDY_MODES[$mode])) {
            $errors['study_mode'] = 'Choose how you attend school.';
        }
        if (mb_strlen($area) < 2) {
            $errors['school_area'] = 'Where is your school? e.g. the trading centre or area.';
        }
        // Boarders live at or next to school: travel time only matters for day and evening students.
        $needsTravel = in_array($mode, ['day', 'evening'], true);
        if ($needsTravel && !isset(self::TRAVEL[$travel])) {
            $errors['travel_time'] = 'Choose how long it takes you to reach school.';
        }
        if (!isset(self::HOME_STUDY[$home])) {
            $errors['home_study'] = 'Choose when you usually have time to study.';
        }

        return [$errors, [
            'study_mode' => $mode,
            'school_area' => mb_substr($area, 0, 120),
            'travel_time' => $needsTravel ? $travel : null,
            'home_study' => $home,
        ]];
    }
}
