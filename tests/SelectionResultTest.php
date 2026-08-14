<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\Exception\BanditException;
use CleatSquad\Bandit\Exception\InvalidSelectionException;
use CleatSquad\Bandit\SelectionResult;
use PHPUnit\Framework\TestCase;

/**
 * The constructor is public, so the shape its docblock promises has to hold at
 * runtime too — a static type is not a guarantee for a library's callers.
 */
final class SelectionResultTest extends TestCase
{
    public function testItCarriesTheDecisionItWasBuiltWith(): void
    {
        $result = new SelectionResult('b', 0.75, ['a' => 0.25, 'b' => 0.75]);

        self::assertSame('b', $result->selectedArm);
        self::assertSame(0.75, $result->sample);
        self::assertSame(['a' => 0.25, 'b' => 0.75], $result->samples);
    }

    public function testIntegerArmKeysAreAccepted(): void
    {
        $result = new SelectionResult(7, 0.5, [7 => 0.5, 9 => 0.1]);

        self::assertSame(7, $result->selectedArm);
    }

    /** A losing draw is legal: only Thompson Sampling picks the maximum. */
    public function testTheSelectedArmNeedNotHoldTheLargestDraw(): void
    {
        $result = new SelectionResult('a', 0.1, ['a' => 0.1, 'b' => 0.9]);

        self::assertSame('a', $result->selectedArm);
    }

    public function testEmptySamplesAreRejected(): void
    {
        $this->expectException(InvalidSelectionException::class);

        new SelectionResult('a', 0.5, []);
    }

    public function testAnArmThatWasNeverDrawnIsRejected(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('has no draw among the samples');

        new SelectionResult('missing', 0.5, ['a' => 0.5]);
    }

    public function testAWinningSampleThatContradictsTheDrawIsRejected(): void
    {
        $this->expectException(InvalidSelectionException::class);
        $this->expectExceptionMessage('does not match the draw');

        new SelectionResult('a', 0.5, ['a' => 0.4]);
    }

    public function testItsExceptionJoinsThePackageHierarchy(): void
    {
        try {
            new SelectionResult('missing', 0.5, ['a' => 0.5]);
            self::fail('An unknown arm must be rejected.');
        } catch (InvalidSelectionException $e) {
            self::assertInstanceOf(BanditException::class, $e);
            self::assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }
}
