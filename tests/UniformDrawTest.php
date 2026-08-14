<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * The gamma sampler takes log() of its uniform draw twice, so both ends of the
 * interval have to be unreachable: 0 gives -INF, 1 collapses the normal draw to
 * zero. That is a property of the divisor, not of the random engine, so it is
 * pinned here rather than left to a sampling test that would almost never hit
 * the boundary.
 */
final class UniformDrawTest extends TestCase
{
    public function testTheTopOfTheDrawRangeStaysBelowOne(): void
    {
        $range = self::drawRange();

        self::assertLessThan(
            1.0,
            ($range - 1) / $range,
            'The largest uniform draw rounds to exactly 1.0, which zeroes the normal draw.',
        );
    }

    public function testTheBottomOfTheDrawRangeStaysAboveZero(): void
    {
        $range = self::drawRange();

        self::assertGreaterThan(
            0.0,
            1 / $range,
            'The smallest uniform draw rounds to exactly 0.0, which makes log() diverge.',
        );
    }

    /** The regression this divisor exists for: PHP_INT_MAX does not have that property. */
    public function testTheRejectedDivisorWouldHaveRoundedUpToOne(): void
    {
        self::assertSame(
            1.0,
            (PHP_INT_MAX - 1) / PHP_INT_MAX,
            'PHP_INT_MAX now keeps its top ratio below 1.0 — the divisor could be widened.',
        );
    }

    public function testDrawsSpanTheIntervalWithoutTouchingItsEnds(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(20260814);
        $low = 1.0;
        $high = 0.0;

        for ($i = 0; $i < 20000; $i++) {
            $draw = $policy->sample(0, 0);
            $low = min($low, $draw);
            $high = max($high, $draw);
        }

        // Beta(1,1) is uniform, so 20k draws should reach close to both ends.
        self::assertGreaterThan(0.0, $low);
        self::assertLessThan(0.001, $low);
        self::assertLessThan(1.0, $high);
        self::assertGreaterThan(0.999, $high);
    }

    private static function drawRange(): int
    {
        $range = (new \ReflectionClass(ThompsonSamplingPolicy::class))->getConstant('DRAW_RANGE');
        self::assertIsInt($range);

        return $range;
    }
}
