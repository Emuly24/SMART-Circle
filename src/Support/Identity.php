<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Who SMART Circle is: vision, mission, goals and values, drawn from the founder's story.
 * Used by the home and About pages so the wording is the same everywhere.
 */
final class Identity
{
    public const VISION = 'A generation of Malawian youth who understand what they learn, apply it in everyday life, and live lives of their own choosing, of benefit to themselves, to others and to the world.';

    public const MISSION = 'To give committed secondary students free, honest and practical support in Mathematics, the sciences and English: reinforcing what their schools teach, connecting every concept to real life in Malawi, and walking with them beyond MANEB.';

    /** @var list<string> */
    public const GOALS = [
        'Every lesson answers "where is this used in real life?"',
        'Every student’s term grade improves, or they get a personal follow-up.',
        'At least 95% of lessons are watched on topics the student’s own school has already taught.',
        'Every Form 2 and Form 4 student gets full revision access before MANEB.',
        'Every student who leaves is followed up as part of our alumni community, for as long as they want.',
        'Every term includes a practical "Apply it" project.',
    ];

    /** @var array<string, array{0: string, 1: string}> key => [value, meaning] */
    public const VALUES = [
        'understand' => ['Understand it', 'No memorising without meaning. We learn why, not only how.'],
        'apply' => ['Apply it', 'Every concept meets everyday life: the farm, the market, the clinic, the workshop.'],
        'excellence' => ['No mediocrity', 'High standards, explained kindly. Good enough is not our goal.'],
        'honesty' => ['Honesty', 'We follow what your school has taught, and we are honest about our work and results.'],
        'discipline' => ['Discipline is the fee', 'SMART Circle costs no money. Students pay with commitment.'],
        'respect' => ['Respect', 'For teachers, schools, classmates and yourself.'],
        'courage' => ['Courage', 'Ask questions, challenge the tutor constructively, and be yourself.'],
        'service' => ['Service without favours', 'Free, always, with zero tolerance for favours of any kind.'],
        'big_picture' => ['See the big picture', 'School is for developing the whole person, not only for a job.'],
    ];
}
