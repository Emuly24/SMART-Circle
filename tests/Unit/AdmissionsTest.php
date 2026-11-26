<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Admissions\ApplicationForm;
use SmartCircle\Application\Admissions\ApplicationService;
use SmartCircle\Application\Admissions\SeatChart;
use SmartCircle\Support\Codes;
use SmartCircle\Support\Subjects;

final class AdmissionsTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function validInput(array $overrides = []): array
    {
        return array_merge([
            'full_name' => '  Chikondi   Banda ',
            'gender' => 'female',
            'form' => '3',
            'school_name' => 'Zomba Catholic Secondary',
            'district' => 'Zomba',
            'phone' => '0999 123 456',
            'email' => '',
            'subjects_taken' => ['mathematics', 'english', 'biology', 'physics', 'chemistry'],
            'help_subjects' => ['physics', 'chemistry'],
            'struggling_topics' => 'Moles',
            'motivation' => 'I want to understand Physics properly because I want to become an engineer.',
            'device' => 'own_phone',
            'data_access' => 'weekly',
            'guardian_name' => 'Grace Banda',
            'guardian_phone' => '0888 765 432',
            'guardian_relationship' => 'Parent',
            'guardian_consent' => '1',
            'truthful' => '1',
            'schooling_reason' => 'understand', 'career_interest' => 'engineering', 'heard_from' => 'teacher',
            'study_hours' => '1_2', 'seriousness' => '5', 'setback' => 'review', 'discipline' => 'stay', 'confidence' => 'ask_class',
            'benefit' => 'I will understand physics and use it on our farm.',
            'helpful_teacher' => 'My maths teacher explained fractions after class until I understood.',
        ], $overrides);
    }

    public function testValidApplicationIsCleaned(): void
    {
        [$errors, $data] = ApplicationForm::validate(self::validInput());

        self::assertSame([], $errors);
        self::assertSame('Chikondi Banda', $data['full_name']);
        self::assertSame('+265999123456', $data['phone']);
        self::assertSame('+265888765432', $data['guardian_phone']);
        self::assertSame('science', $data['track']);
        self::assertSame(['physics', 'chemistry'], $data['help_subjects']);
        self::assertNull($data['email']);
    }

    public function testHumanitiesStudentsCannotGetPhysicsOrChemistryHelp(): void
    {
        [$errors, $data] = ApplicationForm::validate(self::validInput([
            'subjects_taken' => ['mathematics', 'english', 'biology', 'physics'], // no chemistry
            'help_subjects' => ['physics', 'mathematics'],
        ]));

        self::assertSame([], $errors);
        self::assertSame('humanities', $data['track']);
        self::assertSame(['mathematics'], $data['help_subjects'], 'Physics help is dropped for humanities');
    }

    public function testHelpOnlyForSubjectsActuallyTaken(): void
    {
        [$errors] = ApplicationForm::validate(self::validInput([
            'form' => '1',
            'subjects_taken' => ['mathematics'],
            'help_subjects' => ['english'],
        ]));

        self::assertArrayHasKey('help_subjects', $errors);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function badInputs(): array
    {
        return [
            'single name' => [['full_name' => 'Chikondi'], 'full_name'],
            'no gender' => [['gender' => ''], 'gender'],
            'form 5' => [['form' => '5'], 'form'],
            'unknown district' => [['district' => 'Atlantis'], 'district'],
            'bad phone' => [['phone' => '12345'], 'phone'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'short motivation' => [['motivation' => 'Because.'], 'motivation'],
            'no device' => [['device' => 'tv'], 'device'],
            'no guardian phone' => [['guardian_phone' => ''], 'guardian_phone'],
            'no consent' => [['guardian_consent' => ''], 'guardian_consent'],
            'not confirmed true' => [['truthful' => ''], 'truthful'],
        ];
    }

    #[DataProvider('badInputs')]
    public function testInvalidFieldsAreReported(array $override, string $field): void
    {
        [$errors] = ApplicationForm::validate(self::validInput($override));

        self::assertArrayHasKey($field, $errors);
    }

    public function testTracks(): void
    {
        self::assertSame('general', Subjects::track(1, []));
        self::assertSame('general', Subjects::track(2, ['physics']));
        self::assertSame('science', Subjects::track(4, ['physics', 'chemistry']));
        self::assertSame('humanities', Subjects::track(3, ['chemistry', 'biology']));
        self::assertSame(['mathematics', 'biology', 'english'], Subjects::forTrack('humanities'));
        self::assertCount(5, Subjects::forTrack('science'));
    }

    public function testSeatChartKeepsGirlsAndBoysSeatsSeparate(): void
    {
        $chart = new SeatChart(3, [
            ['form' => 3, 'gender' => 'female', 'status' => 'admitted', 'n' => 3],
            ['form' => 3, 'gender' => 'male', 'status' => 'admitted', 'n' => 1],
            ['form' => 3, 'gender' => 'male', 'status' => 'submitted', 'n' => 4],
            ['form' => 3, 'gender' => 'female', 'status' => 'waitlisted', 'n' => 2],
        ]);

        self::assertFalse($chart->hasSeat(3, 'female'), 'Girls full');
        self::assertTrue($chart->hasSeat(3, 'male'), 'Boys still have 2 seats');
        self::assertSame(2, $chart->cell(3, 'male')['open']);
        self::assertSame(4, $chart->cell(3, 'male')['pending']);
        self::assertSame(2, $chart->cell(3, 'female')['waitlisted']);
        self::assertSame(24, $chart->totalSeats());
        self::assertSame(20, $chart->totalOpen());
    }

    public function testCodesAndReferences(): void
    {
        $ref = Codes::applicationReference(2027);
        self::assertMatchesRegularExpression('/^SC-27-[2-9A-HJ-NP-Z]{5}$/', $ref);
        self::assertSame($ref, ApplicationService::formatReference(strtolower(str_replace('-', ' ', $ref))));
        self::assertSame('ABCD2345', Codes::normalize(' abcd-2345 '));
        self::assertDoesNotMatchRegularExpression('/[01IOL]/', Codes::random(200));
    }

    public function testSchemaFileMatchesTheMigrations(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'schema');
        exec('php ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/build-schema.php') . ' ' . escapeshellarg((string) $tmp));
        self::assertFileEquals((string) $tmp, dirname(__DIR__, 2) . '/database/schema.sql', 'Run: php tools/build-schema.php');
        unlink((string) $tmp);
    }
}
