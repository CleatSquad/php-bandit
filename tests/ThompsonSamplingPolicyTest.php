<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use CleatSquad\Bandit\Exception\InvalidArmStateException;
use CleatSquad\Bandit\ThompsonSamplingPolicy;

final class ThompsonSamplingPolicyTest extends TestCase
{
    public function testPosteriorParamsAndMean(): void
    {
        $policy = new ThompsonSamplingPolicy();

        // Prior is Beta(1,1) -> mean is 0.5
        self::assertSame(0.5, $policy->posteriorMean(0, 0));

        // 1 success -> Beta(2,1) -> mean is 2/3
        self::assertEqualsWithDelta(2 / 3, $policy->posteriorMean(1, 0), 0.0001);

        // 1 failure -> Beta(1,2) -> mean is 1/3
        self::assertEqualsWithDelta(1 / 3, $policy->posteriorMean(0, 1), 0.0001);
    }

    public function testPosteriorWeightIncreasesWithEvidence(): void
    {
        $policy = new ThompsonSamplingPolicy();

        // No evidence -> prior variance = 1/12 -> weight is 0.0
        self::assertSame(0.0, $policy->posteriorWeight(0, 0));

        // Adding evidence narrows variance, increasing weight towards 1.0
        $weight1 = $policy->posteriorWeight(5, 5);
        $weight2 = $policy->posteriorWeight(50, 50);

        self::assertGreaterThan(0.0, $weight1);
        self::assertGreaterThan($weight1, $weight2);
        self::assertLessThan(1.0, $weight2);
    }

    public function testDeterministicSamplingWithSeed(): void
    {
        $policy1 = ThompsonSamplingPolicy::withSeed(42);
        $policy2 = ThompsonSamplingPolicy::withSeed(42);

        $draws1 = [];
        $draws2 = [];

        for ($i = 0; $i < 10; $i++) {
            $draws1[] = $policy1->sample(10, 5);
            $draws2[] = $policy2->sample(10, 5);
        }

        self::assertSame($draws1, $draws2, 'Deterministic seeds must produce identical sample sequences.');
    }

    /**
     * @param callable(ThompsonSamplingPolicy, int, int): float $statistic
     */
    #[DataProvider('statistics')]
    public function testNegativeCountsAreRejectedByEveryStatistic(callable $statistic): void
    {
        $this->expectException(InvalidArmStateException::class);
        $statistic(new ThompsonSamplingPolicy(), -1, 0);
    }

    /**
     * @param callable(ThompsonSamplingPolicy, int, int): float $statistic
     */
    #[DataProvider('statistics')]
    public function testNegativeFailureCountsAreRejectedByEveryStatistic(callable $statistic): void
    {
        $this->expectException(InvalidArmStateException::class);
        $statistic(new ThompsonSamplingPolicy(), 0, -1);
    }

    /**
     * @return array<string, array{0: callable(ThompsonSamplingPolicy, int, int): float}>
     */
    public static function statistics(): array
    {
        return [
            'posteriorMean' => [static fn (ThompsonSamplingPolicy $p, int $s, int $f): float => $p->posteriorMean($s, $f)],
            'posteriorVariance' => [static fn (ThompsonSamplingPolicy $p, int $s, int $f): float => $p->posteriorVariance($s, $f)],
            'posteriorWeight' => [static fn (ThompsonSamplingPolicy $p, int $s, int $f): float => $p->posteriorWeight($s, $f)],
            'sample' => [static fn (ThompsonSamplingPolicy $p, int $s, int $f): float => $p->sample($s, $f)],
        ];
    }
}
