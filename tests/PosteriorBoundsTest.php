<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves the bounds posteriorWeight() used to clamp are unreachable, so removing
 * the clamp removes dead code rather than a guarantee.
 */
final class PosteriorBoundsTest extends TestCase
{
    /** Beta(1,1) variance, the normalizer posteriorWeight() divides by. */
    private const PRIOR_VARIANCE = 1.0 / 12.0;

    /**
     * Counts spanning the uninformed corner, small samples, lopsided evidence
     * and very large evidence.
     *
     * @return list<int>
     */
    private const COUNTS = [0, 1, 2, 3, 5, 10, 50, 100, 1000, 100000, 10000000];

    /**
     * The posterior variance never exceeds the prior variance and never reaches
     * zero. This is the invariant the clamp was standing in for.
     */
    #[DataProvider('countGrid')]
    public function testPosteriorVarianceStaysWithinThePriorVariance(int $successes, int $failures): void
    {
        $variance = (new ThompsonSamplingPolicy())->posteriorVariance($successes, $failures);

        self::assertGreaterThan(0.0, $variance);
        self::assertLessThanOrEqual(self::PRIOR_VARIANCE, $variance);
    }

    /** Consequence of the variance bound: the weight lands in [0, 1) unclamped. */
    #[DataProvider('countGrid')]
    public function testPosteriorWeightStaysWithinTheUnitInterval(int $successes, int $failures): void
    {
        $policy = new ThompsonSamplingPolicy();

        $unclamped = 1.0 - $policy->posteriorVariance($successes, $failures) / self::PRIOR_VARIANCE;

        self::assertGreaterThanOrEqual(0.0, $unclamped);
        self::assertLessThan(1.0, $unclamped);
        self::assertSame($policy->posteriorWeight($successes, $failures), $unclamped);
    }

    /** The maximum variance is attained only by the uninformed posterior. */
    public function testOnlyTheUninformedPosteriorReachesThePriorVariance(): void
    {
        $policy = new ThompsonSamplingPolicy();

        self::assertSame(self::PRIOR_VARIANCE, $policy->posteriorVariance(0, 0));
        self::assertSame(0.0, $policy->posteriorWeight(0, 0));

        foreach (self::countGrid() as [$successes, $failures]) {
            if ($successes === 0 && $failures === 0) {
                continue;
            }

            self::assertLessThan(
                self::PRIOR_VARIANCE,
                $policy->posteriorVariance($successes, $failures),
                sprintf('Variance must drop below the prior for (%d, %d).', $successes, $failures),
            );
        }
    }

    /** @return array<string, array{0: int, 1: int}> */
    public static function countGrid(): array
    {
        $grid = [];

        foreach (self::COUNTS as $successes) {
            foreach (self::COUNTS as $failures) {
                $grid[sprintf('%d/%d', $successes, $failures)] = [$successes, $failures];
            }
        }

        return $grid;
    }
}
