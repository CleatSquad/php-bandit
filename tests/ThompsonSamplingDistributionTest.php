<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Distribution-level checks: a sampler can pass every unit test and still draw
 * from the wrong curve. Seeds are fixed, so these assertions are deterministic
 * rather than flaky.
 */
final class ThompsonSamplingDistributionTest extends TestCase
{
    private const SAMPLE_COUNT = 40000;

    /** Kolmogorov-Smirnov 99% critical value, 1.63 / sqrt(n). */
    private const KS_CRITICAL = 1.63 / 200.0; // sqrt(40000) === 200

    /** @var list<float> Lanczos g=7, n=9 series. */
    private const LANCZOS_COEFFICIENTS = [
        676.5203681218851, -1259.1392167224028, 771.32342877765313,
        -176.61502916214059, 12.507343278686905, -0.13857109526572012,
        9.9843695780195716e-6, 1.5056327351493116e-7,
    ];

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function posteriors(): array
    {
        return [
            'uninformed' => [0, 0],
            'balanced' => [5, 5],
            'skewed high' => [40, 10],
            'skewed low' => [1, 99],
            'concentrated' => [500, 500],
        ];
    }

    #[DataProvider('posteriors')]
    public function testSamplesFollowTheBetaPosterior(int $successes, int $failures): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(20260814);

        $draws = [];
        for ($i = 0; $i < self::SAMPLE_COUNT; $i++) {
            $draws[] = $policy->sample($successes, $failures);
        }
        sort($draws);

        $alpha = 1.0 + $successes;
        $beta = 1.0 + $failures;
        $distance = 0.0;

        foreach ($draws as $index => $draw) {
            $expected = self::betaCdf($alpha, $beta, $draw);
            $distance = max(
                $distance,
                abs(($index + 1) / self::SAMPLE_COUNT - $expected),
                abs($expected - $index / self::SAMPLE_COUNT)
            );
        }

        self::assertLessThan(
            self::KS_CRITICAL,
            $distance,
            sprintf('Draws do not match Beta(%.1f, %.1f): KS distance %.5f', $alpha, $beta, $distance)
        );
    }

    #[DataProvider('posteriors')]
    public function testSampleMeanMatchesThePosteriorMean(int $successes, int $failures): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(4242);

        $total = 0.0;
        for ($i = 0; $i < self::SAMPLE_COUNT; $i++) {
            $total += $policy->sample($successes, $failures);
        }

        self::assertEqualsWithDelta(
            $policy->posteriorMean($successes, $failures),
            $total / self::SAMPLE_COUNT,
            0.005
        );
    }

    /**
     * The mean alone does not pin the curve down: a sampler stuck on the mean
     * would pass the test above. The spread has to match too.
     */
    #[DataProvider('posteriors')]
    public function testSampleVarianceMatchesThePosteriorVariance(int $successes, int $failures): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(31337);

        $draws = [];
        $total = 0.0;
        for ($i = 0; $i < self::SAMPLE_COUNT; $i++) {
            $draw = $policy->sample($successes, $failures);
            $draws[] = $draw;
            $total += $draw;
        }

        $mean = $total / self::SAMPLE_COUNT;
        $squaredError = 0.0;
        foreach ($draws as $draw) {
            $squaredError += ($draw - $mean) ** 2;
        }

        $expected = $policy->posteriorVariance($successes, $failures);

        // Relative tolerance: the variance of Beta(501, 501) is four orders of
        // magnitude below that of the prior, so an absolute delta is meaningless.
        self::assertEqualsWithDelta(
            1.0,
            ($squaredError / (self::SAMPLE_COUNT - 1)) / $expected,
            0.05,
            sprintf('Sample spread does not match Beta(%d, %d).', 1 + $successes, 1 + $failures),
        );
    }

    public function testEveryDrawStaysInTheUnitInterval(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(7);

        for ($i = 0; $i < self::SAMPLE_COUNT; $i++) {
            $draw = $policy->sample(3, 7);
            self::assertGreaterThan(0.0, $draw);
            self::assertLessThan(1.0, $draw);
        }
    }

    public function testAnOverwhelminglyBetterArmAlwaysWins(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(99);
        $wins = 0;

        for ($i = 0; $i < 2000; $i++) {
            if ($policy->sample(90, 10) > $policy->sample(10, 90)) {
                $wins++;
            }
        }

        self::assertSame(2000, $wins, 'Exploitation is too weak on non-overlapping posteriors.');
    }

    /**
     * The point of Thompson Sampling: when posteriors overlap, the better arm
     * is favoured but the weaker one still gets explored.
     */
    public function testAMarginallyBetterArmIsFavouredButNotAlwaysChosen(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(2024);
        $wins = 0;

        for ($i = 0; $i < 2000; $i++) {
            if ($policy->sample(60, 40) > $policy->sample(50, 50)) {
                $wins++;
            }
        }

        self::assertGreaterThan(1200, $wins, 'The better arm is not favoured enough.');
        self::assertLessThan(1980, $wins, 'Exploration never happens.');
    }

    /** Regularised incomplete beta function. */
    private static function betaCdf(float $a, float $b, float $x): float
    {
        if ($x <= 0.0) {
            return 0.0;
        }
        if ($x >= 1.0) {
            return 1.0;
        }

        $front = exp(
            self::logGamma($a + $b) - self::logGamma($a) - self::logGamma($b)
            + $a * log($x) + $b * log(1.0 - $x)
        );

        return $x < ($a + 1.0) / ($a + $b + 2.0)
            ? $front * self::betaContinuedFraction($a, $b, $x) / $a
            : 1.0 - $front * self::betaContinuedFraction($b, $a, 1.0 - $x) / $b;
    }

    private static function betaContinuedFraction(float $a, float $b, float $x): float
    {
        $tiny = 1e-300;
        $qab = $a + $b;
        $c = 1.0;
        $d = 1.0 - $qab * $x / ($a + 1.0);
        $d = abs($d) < $tiny ? $tiny : $d;
        $d = 1.0 / $d;
        $result = $d;

        for ($m = 1; $m <= 300; $m++) {
            $m2 = 2 * $m;

            foreach ([
                $m * ($b - $m) * $x / (($a - 1.0 + $m2) * ($a + $m2)),
                -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($a + 1.0 + $m2)),
            ] as $numerator) {
                $d = 1.0 + $numerator * $d;
                $d = abs($d) < $tiny ? $tiny : $d;
                $c = 1.0 + $numerator / $c;
                $c = abs($c) < $tiny ? $tiny : $c;
                $d = 1.0 / $d;
                $result *= $d * $c;
            }

            if (abs($d * $c - 1.0) < 3e-14) {
                break;
            }
        }

        return $result;
    }

    /** Lanczos approximation. */
    private static function logGamma(float $x): float
    {
        if ($x < 0.5) {
            return log(M_PI / sin(M_PI * $x)) - self::logGamma(1.0 - $x);
        }

        $x -= 1.0;
        $accumulator = 0.99999999999980993;

        foreach (self::LANCZOS_COEFFICIENTS as $index => $coefficient) {
            $accumulator += $coefficient / ($x + $index + 1);
        }

        $t = $x + 7.5;

        return 0.5 * log(2 * M_PI) + ($x + 0.5) * log($t) - $t + log($accumulator);
    }
}
