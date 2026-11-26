<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Assessments\Marker;
use SmartCircle\Application\Assessments\QuestionForm;

final class MarkerTest extends TestCase
{
    public function testMultipleChoice(): void
    {
        $d = ['options' => ['A', 'B', 'C'], 'correct' => '1'];
        self::assertSame(2.0, Marker::score('mcq', $d, '1', 2));
        self::assertSame(0.0, Marker::score('mcq', $d, '0', 2));
        self::assertSame(0.0, Marker::score('mcq', $d, '', 2), 'blank answer');
    }

    public function testNumericValueToleranceAndUnits(): void
    {
        $d = ['answer' => 2.5, 'tolerance_pct' => 2, 'unit' => 'm/s', 'unit_required' => true];
        self::assertSame(4.0, Marker::score('numeric', $d, '2.5 m/s', 4));
        self::assertSame(4.0, Marker::score('numeric', $d, '2.54m/s', 4), 'within 2%');
        self::assertSame(0.0, Marker::score('numeric', $d, '2.6 m/s', 4), 'outside 2%');
        self::assertSame(2.0, Marker::score('numeric', $d, '2.5', 4), 'right value, missing unit: half');
        self::assertSame(2.0, Marker::score('numeric', $d, '2.5 km/h', 4), 'wrong unit: half');
        self::assertSame(4.0, Marker::score('numeric', $d, '5/2 m/s', 4), 'fraction');
        self::assertSame(0.0, Marker::score('numeric', $d, 'about two', 4));

        $big = ['answer' => 32000, 'tolerance_pct' => 1, 'unit' => 'J', 'unit_required' => false];
        self::assertSame(3.0, Marker::score('numeric', $big, '3.2e4 J', 3));
        self::assertSame(3.0, Marker::score('numeric', $big, '3.2 x 10^4', 3));
        self::assertSame(3.0, Marker::score('numeric', $big, '32,000', 3));
        self::assertSame(1.0, Marker::score('numeric', ['answer' => -0.75], '−0.75', 1), 'unicode minus');
        self::assertSame(1.0, Marker::score('numeric', ['answer' => 12, 'unit' => 'Ω', 'unit_required' => true], '12 ohms', 1));
    }

    public function testGrammarFillIgnoresCaseSpacesAndEndPunctuation(): void
    {
        $d = ['accepted' => ['has been', "hasn't been"]];
        self::assertSame(1.0, Marker::score('grammar_fill', $d, '  Has   been. ', 1));
        self::assertSame(1.0, Marker::score('grammar_fill', $d, 'hasn’t been', 1), 'curly apostrophe from a phone');
        self::assertSame(0.0, Marker::score('grammar_fill', $d, 'have been', 1));
    }

    public function testShortTextIsLeftForTheTutor(): void
    {
        self::assertNull(Marker::score('short_text', [], 'Because heat makes particles move faster', 3));
    }

    public function testQuestionFormBuildsTypeData(): void
    {
        $v = QuestionForm::validate(['type' => 'mcq', 'form' => 3, 'subject' => 'physics', 'prompt' => 'Which is a vector?',
            'marks' => 1, 'options' => ['Speed', 'Velocity', '', 'Mass'], 'correct' => '1']);
        self::assertSame([], $v['errors']);
        self::assertSame(['Speed', 'Velocity', 'Mass'], $v['clean']['data']['options'], 'blank choices dropped');

        $bad = QuestionForm::validate(['type' => 'numeric', 'form' => 9, 'subject' => 'x', 'prompt' => 'hi', 'marks' => 0, 'answer' => 'abc']);
        self::assertSame(['form', 'subject', 'prompt', 'marks', 'answer'], array_keys($bad['errors']));

        $g = QuestionForm::validate(['type' => 'grammar_fill', 'form' => 1, 'subject' => 'english', 'prompt' => 'She ___ here since May.',
            'marks' => 1, 'accepted' => "has been\r\n\r\nhas lived\n"]);
        self::assertSame(['has been', 'has lived'], $g['clean']['data']['accepted']);
    }
}
