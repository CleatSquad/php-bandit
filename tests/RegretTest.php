<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end learning behaviour: the sampler is fed back its own outcomes, as
 * a caller would, and the run is judged on regret — the reward lost against an
 * oracle that always plays the best arm.
 *
 * Thompson Sampling's guarantee is logarithmic regret, so what these tests
 * assert is the shape of the curve, not a single-run number: regret per round
 * has to keep falling as the horizon grows.
 */
final class RegretTest extends TestCase
{
    /** True conversion rates the simulated environment pays out on. */
    private const RATES = ['poor' => 0.10, 'fair' => 0.30, 'good' => 0.50, 'best' => 0.60];

    private const HORIZON = 20000;

    public function testRegretGrowsSlowerThanTheHorizon(): void
    {
        $regret = $this->simulate(self::HORIZON, 20260814);

        // A policy that never learned would lose (0.60 - mean rate) per round,
        // roughly 0.24 * horizon. Logarithmic regret is orders below that.
        self::assertLessThan(
            0.02 * self::HORIZON,
            $regret[self::HORIZON - 1],
            'Cumulative regret is growing linearly: the policy is not learning.',
        );
    }

    public function testRegretPerRoundKeepsFalling(): void
    {
        $regret = $this->simulate(self::HORIZON, 4242);

        $early = $regret[999] / 1000;
        $middle = $regret[4999] / 5000;
        $late = $regret[self::HORIZON - 1] / self::HORIZON;

        self::assertLessThan($early, $middle, 'Regret per round did not improve after the first thousand rounds.');
        self::assertLessThan($middle, $late, 'Regret per round stopped improving: exploration never settles.');
    }

    /**
     * Logarithmic regret means the second half of a run costs no more than the
     * first, even though it is the same number of rounds.
     */
    public function testTheSecondHalfOfARunCostsLessThanTheFirst(): void
    {
        $regret = $this->simulate(self::HORIZON, 7);

        $firstHalf = $regret[self::HORIZON / 2 - 1];
        $secondHalf = $regret[self::HORIZON - 1] - $firstHalf;

        self::assertLessThan($firstHalf, $secondHalf);
    }

    public function testTheBestArmEndsUpTakingTheVastMajorityOfTraffic(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(99);
        $environment = new \Random\Randomizer(new \Random\Engine\Mt19937(99));
        $arms = array_map(static fn (): ArmState => new ArmState(0, 0), self::RATES);
        $pulls = array_fill_keys(array_keys(self::RATES), 0);

        for ($round = 0; $round < self::HORIZON; $round++) {
            $selected = $policy->select($arms)->selectedArm;
            self::assertIsString($selected);

            $pulls[$selected]++;
            $arms[$selected] = $environment->getInt(1, 1000) <= self::RATES[$selected] * 1000
                ? $arms[$selected]->withSuccess()
                : $arms[$selected]->withFailure();
        }

        self::assertGreaterThan(0.85 * self::HORIZON, $pulls['best'], 'The best arm is under-exploited.');

        foreach ($pulls as $arm => $count) {
            self::assertGreaterThan(0, $count, sprintf('Arm %s was never explored.', $arm));
        }
    }

    /**
     * Runs one bandit episode and returns cumulative regret after each round.
     *
     * @return list<float>
     */
    private function simulate(int $horizon, int $seed): array
    {
        $policy = ThompsonSamplingPolicy::withSeed($seed);
        $environment = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
        $arms = array_map(static fn (): ArmState => new ArmState(0, 0), self::RATES);

        $best = max(self::RATES);
        $cumulative = 0.0;
        $regret = [];

        for ($round = 0; $round < $horizon; $round++) {
            $selected = $policy->select($arms)->selectedArm;
            self::assertIsString($selected);

            $cumulative += $best - self::RATES[$selected];
            $regret[] = $cumulative;

            $arms[$selected] = $environment->getInt(1, 1000) <= self::RATES[$selected] * 1000
                ? $arms[$selected]->withSuccess()
                : $arms[$selected]->withFailure();
        }

        return $regret;
    }
}
