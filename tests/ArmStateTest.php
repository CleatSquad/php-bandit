<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests;

use CleatSquad\Bandit\ArmState;
use CleatSquad\Bandit\Exception\BanditException;
use CleatSquad\Bandit\Exception\InvalidArmStateException;
use PHPUnit\Framework\TestCase;

final class ArmStateTest extends TestCase
{
    public function testNegativeSuccessesAreRejected(): void
    {
        $this->expectException(InvalidArmStateException::class);
        new ArmState(-1, 0);
    }

    public function testNegativeFailuresAreRejected(): void
    {
        $this->expectException(InvalidArmStateException::class);
        new ArmState(0, -1);
    }

    public function testRejectionIsCatchableAsAPackageAndAnSplException(): void
    {
        try {
            new ArmState(-5, -3);
            self::fail('An impossible arm state must be rejected.');
        } catch (InvalidArmStateException $e) {
            self::assertInstanceOf(BanditException::class, $e);
            self::assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    public function testFromTrialsSplitsSuccessesAndFailures(): void
    {
        $state = ArmState::fromTrials(trials: 100, successes: 63);

        self::assertSame(63, $state->successes);
        self::assertSame(37, $state->failures);
    }

    public function testFromTrialsAcceptsTheEmptyState(): void
    {
        $state = ArmState::fromTrials(0, 0);

        self::assertSame(0, $state->successes);
        self::assertSame(0, $state->failures);
    }

    /**
     * The messages are asserted, not just the class. Every guard in fromTrials()
     * is followed by another one that rejects the same input for a different
     * reason, so a missing guard would still throw — just about the wrong thing.
     */
    public function testFromTrialsRejectsMoreSuccessesThanTrials(): void
    {
        $this->expectException(InvalidArmStateException::class);
        $this->expectExceptionMessage('Successes cannot exceed trials, 11 of 10 given.');

        ArmState::fromTrials(10, 11);
    }

    public function testFromTrialsRejectsNegativeTrials(): void
    {
        $this->expectException(InvalidArmStateException::class);
        $this->expectExceptionMessage('Trials must be zero or greater, -1 given.');

        ArmState::fromTrials(-1, 0);
    }

    public function testFromTrialsRejectsNegativeSuccesses(): void
    {
        $this->expectException(InvalidArmStateException::class);
        $this->expectExceptionMessage('Successes must be zero or greater, -1 given.');

        ArmState::fromTrials(10, -1);
    }

    public function testTrialsIsTheSumOfObservations(): void
    {
        self::assertSame(0, (new ArmState(0, 0))->trials());
        self::assertSame(16, (new ArmState(12, 4))->trials());
    }

    public function testWithSuccessAndWithFailureReturnNewInstances(): void
    {
        $state = new ArmState(12, 4);

        $success = $state->withSuccess();
        $failure = $state->withFailure();

        self::assertSame(13, $success->successes);
        self::assertSame(4, $success->failures);
        self::assertSame(12, $failure->successes);
        self::assertSame(5, $failure->failures);

        // The original observation is untouched by either transition.
        self::assertSame(12, $state->successes);
        self::assertSame(4, $state->failures);
    }

    public function testTransitionsAreCumulative(): void
    {
        $state = (new ArmState(0, 0))
            ->withSuccess()
            ->withSuccess()
            ->withFailure()
            ->withSuccess();

        self::assertSame(3, $state->successes);
        self::assertSame(1, $state->failures);
        self::assertSame(4, $state->trials());
    }
}
