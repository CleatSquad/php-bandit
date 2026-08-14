<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\BanditPolicyInterface;
use CleatSquad\Bandit\Exception\BanditException;
use CleatSquad\Bandit\Exception\EmptyArmSetException;
use CleatSquad\Bandit\Tests\Support\ConstantEngine;
use CleatSquad\Bandit\ThompsonSamplingPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural checks on the decision contract. Assertions are on properties of
 * the selection, not on which arm a given seed happens to return.
 */
final class SelectionTest extends TestCase
{
    private const DECISIONS = 2000;

    public function testPolicyFulfilsTheDecisionContract(): void
    {
        self::assertInstanceOf(BanditPolicyInterface::class, new ThompsonSamplingPolicy());
    }

    public function testDominantArmIsSelectedMostOfTheTime(): void
    {
        $counts = $this->countSelections([
            'weak' => new ArmState(1, 40),
            'strong' => new ArmState(60, 2),
        ]);

        self::assertGreaterThan(
            0.9 * self::DECISIONS,
            $counts['strong'],
            'A clearly dominant arm must win the vast majority of draws.',
        );
    }

    public function testTrailingArmIsStillExplored(): void
    {
        $counts = $this->countSelections([
            'trailing' => new ArmState(6, 10),
            'leading' => new ArmState(12, 8),
        ]);

        self::assertGreaterThan(
            0,
            $counts['trailing'],
            'Exploration is the point: a trailing arm must still be picked sometimes.',
        );
    }

    public function testUninformedArmsAreSelectedUniformly(): void
    {
        $counts = $this->countSelections([
            'a' => new ArmState(0, 0),
            'b' => new ArmState(0, 0),
            'c' => new ArmState(0, 0),
        ]);

        foreach ($counts as $arm => $count) {
            $share = $count / self::DECISIONS;
            self::assertGreaterThan(0.28, $share, sprintf('Arm %s is starved.', $arm));
            self::assertLessThan(0.39, $share, sprintf('Arm %s is favoured.', $arm));
        }
    }

    public function testSamplesCoverExactlyTheInputKeys(): void
    {
        $arms = [
            'arm_a' => new ArmState(3, 1),
            7 => new ArmState(5, 5),
            'arm_c' => new ArmState(0, 0),
        ];

        $result = ThompsonSamplingPolicy::withSeed(11)->select($arms);

        self::assertSame(array_keys($arms), array_keys($result->samples));
        self::assertContains($result->selectedArm, array_keys($arms));
    }

    public function testWinningSampleIsTheMaximum(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(12);
        $arms = [
            'a' => new ArmState(3, 1),
            'b' => new ArmState(5, 5),
            'c' => new ArmState(0, 7),
        ];

        for ($i = 0; $i < 200; $i++) {
            $result = $policy->select($arms);

            self::assertSame($result->samples[$result->selectedArm], $result->sample);
            self::assertSame(max($result->samples), $result->sample);
        }
    }

    public function testSamplesLieInTheOpenUnitInterval(): void
    {
        $policy = ThompsonSamplingPolicy::withSeed(13);
        $arms = ['a' => new ArmState(0, 0), 'b' => new ArmState(200, 3)];

        for ($i = 0; $i < 200; $i++) {
            foreach ($policy->select($arms)->samples as $sample) {
                self::assertGreaterThan(0.0, $sample);
                self::assertLessThan(1.0, $sample);
            }
        }
    }

    /**
     * With real randomness two draws are never equal, so the tie-break is
     * unobservable. A constant engine makes it observable: every arm in the
     * same state draws the same sample, and the first one has to win.
     */
    public function testTheFirstArmWinsATie(): void
    {
        $policy = new ThompsonSamplingPolicy(new \Random\Randomizer(new ConstantEngine()));

        $result = $policy->select([
            'first' => new ArmState(0, 0),
            'second' => new ArmState(0, 0),
            'third' => new ArmState(0, 0),
        ]);

        self::assertSame('first', $result->selectedArm);
        self::assertSame($result->samples['first'], $result->samples['second']);
        self::assertSame($result->samples['first'], $result->samples['third']);
    }

    public function testSingleArmIsAlwaysSelected(): void
    {
        $result = ThompsonSamplingPolicy::withSeed(14)->select(['only' => new ArmState(0, 99)]);

        self::assertSame('only', $result->selectedArm);
        self::assertSame(['only'], array_keys($result->samples));
    }

    public function testEmptyArmSetThrows(): void
    {
        try {
            ThompsonSamplingPolicy::withSeed(15)->select([]);
            self::fail('Selecting from an empty set must be rejected.');
        } catch (EmptyArmSetException $e) {
            self::assertInstanceOf(BanditException::class, $e);
            self::assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    public function testSelectIsDeterministicUnderTheSameSeed(): void
    {
        $arms = ['a' => new ArmState(4, 4), 'b' => new ArmState(9, 2)];

        $first = ThompsonSamplingPolicy::withSeed(16);
        $second = ThompsonSamplingPolicy::withSeed(16);

        for ($i = 0; $i < 20; $i++) {
            $left = $first->select($arms);
            $right = $second->select($arms);

            self::assertSame($left->selectedArm, $right->selectedArm);
            self::assertSame($left->sample, $right->sample);
            self::assertSame($left->samples, $right->samples);
        }
    }

    public function testSelectArmMatchesSelect(): void
    {
        $arms = ['a' => new ArmState(4, 4), 'b' => new ArmState(9, 2)];

        $shorthand = ThompsonSamplingPolicy::withSeed(17)->selectArm($arms);
        $full = ThompsonSamplingPolicy::withSeed(17)->select($arms);

        self::assertSame($full->selectedArm, $shorthand);
    }

    /**
     * @param array<string, ArmState> $arms
     * @return array<string, int>
     */
    private function countSelections(array $arms): array
    {
        $policy = ThompsonSamplingPolicy::withSeed(20260814);
        $counts = array_fill_keys(array_keys($arms), 0);

        for ($i = 0; $i < self::DECISIONS; $i++) {
            $selected = $policy->select($arms)->selectedArm;
            self::assertIsString($selected);
            $counts[$selected]++;
        }

        return $counts;
    }
}
