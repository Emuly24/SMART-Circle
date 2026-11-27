<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Admissions\Pledge;
use SmartCircle\Application\Admissions\Questionnaire;
use SmartCircle\Application\Admissions\SignupForm;

final class IdentityTest extends TestCase
{
    private const ANSWERS = [
        'schooling_reason' => 'develop', 'career_interest' => 'other', 'career_interest_other' => 'Pilot',
        'heard_from' => 'friend', 'study_hours' => 'under_30', 'seriousness' => '4', 'setback' => 'give_up',
        'discipline' => 'study_first', 'confidence' => 'quiet',
        'benefit' => 'I will finally understand chemistry equations properly.',
        'helpful_teacher' => 'Our biology teacher brought real leaves to class to show us.',
    ];

    public function testQuestionnaireValidAnswers(): void
    {
        [$errors, $clean] = Questionnaire::validate(self::ANSWERS);
        self::assertSame([], $errors);
        self::assertSame('Pilot', $clean['career_interest_other']);
    }

    public function testQuestionnaireNeedsEveryAnswerAndOtherText(): void
    {
        [$errors] = Questionnaire::validate(['career_interest' => 'other', 'schooling_reason' => 'nonsense', 'benefit' => 'short']);
        self::assertArrayHasKey('heard_from', $errors);
        self::assertArrayHasKey('schooling_reason', $errors, 'unknown option refused');
        self::assertArrayHasKey('career_interest', $errors, '"something else" needs words');
        self::assertArrayHasKey('benefit', $errors);
        self::assertArrayHasKey('helpful_teacher', $errors);
    }

    public function testSignalsSummariseScenarioAnswers(): void
    {
        [, $clean] = Questionnaire::validate(self::ANSWERS);
        $s = Questionnaire::summary($clean);
        // growth: develop, seriousness 4, study_first; concern: under_30, give_up, quiet
        self::assertSame(['growth' => 3, 'neutral' => 2, 'concern' => 3], $s['counts']);
        self::assertStringContainsString('Something else: Pilot', implode('|', array_column($s['rows'], 'answer')));
    }

    public function testPledgeNeedsEveryVowSignatureAndGuardian(): void
    {
        $all = array_keys(Pledge::VOWS);
        self::assertSame([], Pledge::validate(['vows' => $all, 'signed_name' => '  chikondi   BANDA ', 'guardian' => '1'], 'Chikondi Banda'));
        $e = Pledge::validate(['vows' => array_slice($all, 1), 'signed_name' => 'C. Banda', 'guardian' => ''], 'Chikondi Banda');
        self::assertSame(['vows', 'signed_name'], array_keys($e));
        self::assertArrayHasKey('maneb', Pledge::VOWS);
        self::assertStringContainsString('no one is ever punished', Pledge::VOWS['maneb'][1], 'sharing results stays voluntary');
    }

    public function testSignupIncludesEveningClassesAndTravelOnlyForDayStudents(): void
    {
        [$e, $d] = SignupForm::validate(['study_mode' => 'evening', 'school_area' => 'Area 25', 'travel_time' => '15_30', 'home_study' => 'morning']);
        self::assertSame([], $e);
        self::assertSame('15_30', $d['travel_time']);

        [$e] = SignupForm::validate(['study_mode' => 'day', 'school_area' => 'Kanengo', 'home_study' => 'evening']);
        self::assertArrayHasKey('travel_time', $e, 'day scholars say how far they travel');

        [$e, $d] = SignupForm::validate(['study_mode' => 'boarder', 'school_area' => 'Dedza', 'travel_time' => 'over_60', 'home_study' => 'weekends']);
        self::assertSame([], $e);
        self::assertNull($d['travel_time'], 'boarders live at school');
    }
}
