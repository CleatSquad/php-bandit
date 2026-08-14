<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use PHPUnit\Framework\TestCase;
use CleatSquad\Bandit\ArmState;
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

    public function testSelectBestArm(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(100);

        $arms = [
            'arm_a' => new ArmState(1, 20),
            'arm_b' => new ArmState(50, 2),
        ];

        $best = $policy->selectBestArm($arms);
        self::assertSame('arm_b', $best);
    }
}
