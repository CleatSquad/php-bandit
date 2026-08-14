<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

use CleatSquad\Bandit\Exception\EmptyArmSetException;
use CleatSquad\Bandit\Exception\InvalidArmStateException;

/**
 * Beta-Bernoulli Thompson Sampling, native PHP.
 * Alpha and Beta parameters are successes/failures plus the Beta(1,1) uninformative prior.
 */
final class ThompsonSamplingPolicy implements BanditPolicyInterface
{
    /** Beta(1,1) variance — the uninformed prior's uncertainty, used to normalize posteriorWeight() to 0..1. */
    private const PRIOR_VARIANCE = 1.0 / 12.0;

    private readonly \Random\Randomizer $randomizer;

    public function __construct(?\Random\Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new \Random\Randomizer();
    }

    /** Deterministic randomizer seeded for reproducible execution and tests. */
    public static function withSeed(int $seed): self
    {
        return new self(new \Random\Randomizer(new \Random\Engine\Mt19937($seed)));
    }

    /**
     * Draws once from every arm's posterior and returns the winner of that draw.
     * The pick is stochastic by design: a trailing arm still gets explored.
     *
     * @param array<string|int, ArmState> $arms
     * @throws EmptyArmSetException
     */
    public function select(array $arms): SelectionResult
    {
        if ($arms === []) {
            throw EmptyArmSetException::create();
        }

        $samples = [];
        $selectedArm = array_key_first($arms);
        $bestSample = -1.0;

        foreach ($arms as $armId => $state) {
            $sample = $this->sample($state->successes, $state->failures);
            $samples[$armId] = $sample;

            if ($sample > $bestSample) {
                $bestSample = $sample;
                $selectedArm = $armId;
            }
        }

        return new SelectionResult($selectedArm, $bestSample, $samples);
    }

    /**
     * Shorthand for select() when only the winning key is needed.
     *
     * @param array<string|int, ArmState> $arms
     * @throws EmptyArmSetException
     */
    public function selectArm(array $arms): string|int
    {
        return $this->select($arms)->selectedArm;
    }

    /**
     * @param array<string|int, ArmState> $arms
     * @throws EmptyArmSetException
     * @deprecated since 0.2.0, use selectArm() or select(). Removed in 0.3.0.
     *             The name promised an argmax; the pick is a posterior draw.
     */
    public function selectBestArm(array $arms): string|int
    {
        return $this->selectArm($arms);
    }

    /** @throws InvalidArmStateException */
    public function posteriorMean(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        return $alpha / ($alpha + $beta);
    }

    /**
     * Confidence in the posterior mean, 0 when uninformed and approaching 1 with evidence.
     *
     * @throws InvalidArmStateException
     */
    public function posteriorWeight(int $successes, int $failures): float
    {
        // Under the Beta(1,1) prior the posterior variance peaks at the prior's
        // own 1/12, so this ratio is in (0, 1] and the result in [0, 1). No clamp.
        return 1.0 - $this->posteriorVariance($successes, $failures) / self::PRIOR_VARIANCE;
    }

    /** @throws InvalidArmStateException */
    public function posteriorVariance(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        return ($alpha * $beta) / (($alpha + $beta) ** 2 * ($alpha + $beta + 1));
    }

    /** @throws InvalidArmStateException */
    public function sample(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        $x = $this->sampleGamma($alpha);
        $y = $this->sampleGamma($beta);
        return $x / ($x + $y);
    }

    /**
     * @return array{0: float, 1: float}
     * @throws InvalidArmStateException
     */
    private function posteriorParams(int $successes, int $failures): array
    {
        if ($successes < 0) {
            throw InvalidArmStateException::negativeCount('Successes', $successes);
        }

        if ($failures < 0) {
            throw InvalidArmStateException::negativeCount('Failures', $failures);
        }

        return [1.0 + $successes, 1.0 + $failures];
    }

    /**
     * Marsaglia & Tsang (2000) — O(1) rejection sampling, valid for shape >= 1.
     */
    private function sampleGamma(float $shape): float
    {
        $d = $shape - 1.0 / 3.0;
        $c = 1.0 / sqrt(9.0 * $d);

        while (true) {
            do {
                $x = $this->sampleStandardNormal();
                $v = (1.0 + $c * $x) ** 3;
            } while ($v <= 0.0);

            $u = $this->uniformOpen01();
            if (log($u) < 0.5 * $x ** 2 + $d - $d * $v + $d * log($v)) {
                return $d * $v;
            }
        }
    }

    /** Box-Muller transformation. */
    private function sampleStandardNormal(): float
    {
        $u1 = $this->uniformOpen01();
        $u2 = $this->uniformOpen01();
        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    /** Uniform(0,1), exclusive both ends. */
    private function uniformOpen01(): float
    {
        return ($this->randomizer->getInt(1, PHP_INT_MAX - 1)) / PHP_INT_MAX;
    }
}
