<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\ThompsonSamplingPolicy;
use CleatSquad\Bandit\Tests\Support\Generator;
use PHPUnit\Framework\TestCase;

/**
 * Invariants that must hold for every input, checked against generated cases
 * rather than hand-picked ones. Each test states the law it defends; a failure
 * message carries the counter-example.
 */
final class PropertyTest extends TestCase
{
    private const CASES = 300;

    public function testTrialsAlwaysEqualSuccessesPlusFailures(): void
    {
        $generator = Generator::seeded(101);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();

            self::assertSame(
                $state->successes + $state->failures,
                $state->trials(),
                $this->describe($state),
            );
        }
    }

    public function testRecordingAnObservationAddsExactlyOneTrialAndLeavesTheOriginalIntact(): void
    {
        $generator = Generator::seeded(102);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();
            $successes = $state->successes;
            $failures = $state->failures;

            $won = $state->withSuccess();
            $lost = $state->withFailure();

            self::assertSame($state->trials() + 1, $won->trials(), $this->describe($state));
            self::assertSame($state->trials() + 1, $lost->trials(), $this->describe($state));
            self::assertSame($successes + 1, $won->successes, $this->describe($state));
            self::assertSame($failures + 1, $lost->failures, $this->describe($state));

            self::assertSame($successes, $state->successes, 'withSuccess() mutated its receiver.');
            self::assertSame($failures, $state->failures, 'withFailure() mutated its receiver.');
        }
    }

    public function testFromTrialsRoundTripsThroughTheConstructor(): void
    {
        $generator = Generator::seeded(103);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();
            $rebuilt = ArmState::fromTrials($state->trials(), $state->successes);

            self::assertEquals($state, $rebuilt, $this->describe($state));
        }
    }

    public function testThePosteriorMeanIsTheBetaMeanAndStaysInsideTheUnitInterval(): void
    {
        $policy = new ThompsonSamplingPolicy();
        $generator = Generator::seeded(104);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();
            $mean = $policy->posteriorMean($state->successes, $state->failures);

            self::assertEqualsWithDelta(
                (1.0 + $state->successes) / (2.0 + $state->trials()),
                $mean,
                1e-12,
                $this->describe($state),
            );
            self::assertGreaterThan(0.0, $mean, $this->describe($state));
            self::assertLessThan(1.0, $mean, $this->describe($state));
        }
    }

    /**
     * More evidence in the same proportion narrows the posterior. Stated on a
     * scaled-up state rather than on a single extra observation, which can
     * widen a lopsided posterior instead of narrowing it.
     */
    public function testMoreEvidenceInTheSameProportionRaisesTheWeight(): void
    {
        $policy = new ThompsonSamplingPolicy();
        $generator = Generator::seeded(105);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();

            if ($state->trials() === 0) {
                continue;
            }

            $scaled = new ArmState($state->successes * 4, $state->failures * 4);

            self::assertGreaterThan(
                $policy->posteriorVariance($scaled->successes, $scaled->failures),
                $policy->posteriorVariance($state->successes, $state->failures),
                $this->describe($state),
            );
            self::assertGreaterThan(
                $policy->posteriorWeight($state->successes, $state->failures),
                $policy->posteriorWeight($scaled->successes, $scaled->failures),
                $this->describe($state),
            );
        }
    }

    public function testTheWeightIsAlwaysAProportionOfTheUninformedUncertainty(): void
    {
        $policy = new ThompsonSamplingPolicy();
        $generator = Generator::seeded(106);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();
            $weight = $policy->posteriorWeight($state->successes, $state->failures);

            self::assertGreaterThanOrEqual(0.0, $weight, $this->describe($state));
            self::assertLessThan(1.0, $weight, $this->describe($state));
        }
    }

    public function testEveryDrawFallsStrictlyInsideTheUnitInterval(): void
    {
        $generator = Generator::seeded(107);

        for ($i = 0; $i < self::CASES; $i++) {
            $state = $generator->armState();
            $draw = ThompsonSamplingPolicy::withSeed($generator->seed())
                ->sample($state->successes, $state->failures);

            self::assertGreaterThan(0.0, $draw, $this->describe($state));
            self::assertLessThan(1.0, $draw, $this->describe($state));
            self::assertFalse(is_nan($draw), $this->describe($state));
        }
    }

    public function testASelectionOnlyEverReportsArmsItWasGiven(): void
    {
        $generator = Generator::seeded(108);

        for ($i = 0; $i < self::CASES; $i++) {
            $arms = $generator->armSet();
            $result = ThompsonSamplingPolicy::withSeed($generator->seed())->select($arms);

            self::assertSame(array_keys($arms), array_keys($result->samples));
            self::assertArrayHasKey($result->selectedArm, $arms);
            self::assertSame($result->samples[$result->selectedArm], $result->sample);
            self::assertSame(max($result->samples), $result->sample);
        }
    }

    public function testTheShorthandNeverDisagreesWithTheFullSelection(): void
    {
        $generator = Generator::seeded(109);

        for ($i = 0; $i < self::CASES; $i++) {
            $arms = $generator->armSet();
            $seed = $generator->seed();

            self::assertSame(
                ThompsonSamplingPolicy::withSeed($seed)->select($arms)->selectedArm,
                ThompsonSamplingPolicy::withSeed($seed)->selectArm($arms),
            );
        }
    }

    public function testTheSameSeedAlwaysReplaysTheSameDecision(): void
    {
        $generator = Generator::seeded(110);

        for ($i = 0; $i < self::CASES; $i++) {
            $arms = $generator->armSet();
            $seed = $generator->seed();

            self::assertEquals(
                ThompsonSamplingPolicy::withSeed($seed)->select($arms),
                ThompsonSamplingPolicy::withSeed($seed)->select($arms),
            );
        }
    }

    private function describe(ArmState $state): string
    {
        return sprintf('Counter-example: ArmState(%d, %d).', $state->successes, $state->failures);
    }
}
