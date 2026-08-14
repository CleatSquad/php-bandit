<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Benchmarks;

use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * What a decision costs. The gamma sampler is a rejection loop, so its cost is
 * an expected value rather than a fixed one — hence several arm-set sizes and
 * several posterior shapes.
 */
#[BeforeMethods('setUp')]
final class SelectionBench
{
    private ThompsonSamplingPolicy $policy;

    /** @var array<int, non-empty-array<string, ArmState>> */
    private array $armSets = [];

    public function setUp(): void
    {
        $this->policy = ThompsonSamplingPolicy::withSeed(20260814);

        foreach ([2, 10, 100] as $size) {
            $arms = [];
            for ($i = 0; $i < $size; $i++) {
                $arms[sprintf('arm_%d', $i)] = new ArmState($i * 3, $i * 2 + 1);
            }

            $this->armSets[$size] = $arms;
        }
    }

    /** @param array{arms: int} $params */
    #[ParamProviders('provideArmSetSizes')]
    public function benchSelect(array $params): void
    {
        $this->policy->select($this->armSets[$params['arms']]);
    }

    /** @param array{successes: int, failures: int} $params */
    #[ParamProviders('providePosteriors')]
    #[Revs(10000)]
    public function benchSample(array $params): void
    {
        $this->policy->sample($params['successes'], $params['failures']);
    }

    #[Revs(10000)]
    public function benchPosteriorMean(): void
    {
        $this->policy->posteriorMean(120, 45);
    }

    /** @return \Generator<string, array{arms: int}> */
    public static function provideArmSetSizes(): \Generator
    {
        yield '2 arms' => ['arms' => 2];
        yield '10 arms' => ['arms' => 10];
        yield '100 arms' => ['arms' => 100];
    }

    /** @return \Generator<string, array{successes: int, failures: int}> */
    public static function providePosteriors(): \Generator
    {
        yield 'uninformed' => ['successes' => 0, 'failures' => 0];
        yield 'balanced' => ['successes' => 50, 'failures' => 50];
        yield 'concentrated' => ['successes' => 5000, 'failures' => 5000];
    }
}
