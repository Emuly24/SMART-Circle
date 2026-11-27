<?php

declare(strict_types=1);

namespace SmartCircle\Application\Team;

/**
 * What a volunteer tutor agrees to before joining the SMART Circle Team.
 * Changing the wording means a new VERSION; existing members keep the version they agreed to.
 */
final class TeamAgreement
{
    public const VERSION = '2026-11';

    public const PHILOSOPHY = 'SMART Circle exists so that young people in Malawi become capable, honest and happy: '
        . 'understanding why what they learn matters, where it is used in real life, and believing they can do it. '
        . 'It is free, always, and it is built on trust.';

    /** @var list<array{0: string, 1: string}> principle and what it means in practice */
    public const PRINCIPLES = [
        ['Free, always, and no favours of any kind', 'I never ask for or accept money, gifts or favours, including sexual favours, from any student or family. Help is never traded.'],
        ['Honesty', 'Lessons follow what each student’s own school has taught. I do not open topics early myself; that is the admin’s decision.'],
        ['Encouragement, not punishment', 'I mark with “Got it”, “Partly there” or “Not yet”, always saying what went well, where it went off track and the next step. No red crosses, no shaming.'],
        ['Real life', 'Every lesson answers “why does this matter, and where is it used in Malawi?”'],
        ['Safeguarding', 'I talk to students only inside SMART Circle, never meet a student alone, and report any worry about a child’s safety to the admin at once.'],
        ['Privacy', 'I keep students’ names, numbers, marks and stories private, and never share them outside SMART Circle.'],
        ['Everything is kept', 'Messages, marks and decisions are kept and cannot be deleted. This protects students and tutors alike.'],
    ];

    /** @var list<string> */
    public const DUTIES = [
        'Teach the subjects I have been given: record clear replay lessons on the board, with the real-life sections filled in.',
        'Prepare notes, slides and exercises for those topics.',
        'Mark work in my subjects within 7 days, with encouraging feedback, and answer re-check requests fairly.',
        'Look at topic requests in my subjects and plan lessons for the most needed ones.',
        'Offer office hours when I can, and reply to students’ messages within 3 days.',
        'Tell the admin early if I need a break or cannot continue, so students are not left waiting.',
        'Follow the admin’s decisions on admissions, early access, private rooms and anything about a student’s welfare.',
    ];
}
