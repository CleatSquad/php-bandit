<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

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

    public function posteriorMean(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        return $alpha / ($alpha + $beta);
    }

    public function posteriorWeight(int $successes, int $failures): float
    {
        $variance = $this->posteriorVariance($successes, $failures);
        return max(0.0, min(1.0, 1.0 - $variance / self::PRIOR_VARIANCE));
    }

    public function posteriorVariance(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        return ($alpha * $beta) / (($alpha + $beta) ** 2 * ($alpha + $beta + 1));
    }

    public function sample(int $successes, int $failures): float
    {
        [$alpha, $beta] = $this->posteriorParams($successes, $failures);
        $x = $this->sampleGamma($alpha);
        $y = $this->sampleGamma($beta);
        return $x / ($x + $y);
    }

    /**
     * Selects the arm key with the highest sampled reward value.
     *
     * @param array<string|int, ArmState> $arms
     */
    public function selectBestArm(array $arms): string|int
    {
        if (empty($arms)) {
            throw new \InvalidArgumentException('Cannot select best arm from an empty array.');
        }

        // Seeded with the first arm rather than null: a draw is always in (0,1),
        // so the loop replaces it, and the return type stays non-nullable.
        $bestArm = array_key_first($arms);
        $highestSample = -1.0;

        foreach ($arms as $armId => $state) {
            $sample = $this->sample($state->successes, $state->failures);
            if ($sample > $highestSample) {
                $highestSample = $sample;
                $bestArm = $armId;
            }
        }

        return $bestArm;
    }

    /** @return array{0: float, 1: float} */
    private function posteriorParams(int $successes, int $failures): array
    {
        return [1.0 + max(0, $successes), 1.0 + max(0, $failures)];
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
