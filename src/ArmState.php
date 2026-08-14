<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

use CleatSquad\Bandit\Exception\InvalidArmStateException;

/**
 * Immutable success/failure tally backing one arm's posterior.
 * Counts are validated here so the posterior never has to repair them.
 */
final readonly class ArmState
{
    /** @throws InvalidArmStateException */
    public function __construct(
        public int $successes,
        public int $failures,
    ) {
        if ($successes < 0) {
            throw InvalidArmStateException::negativeCount('Successes', $successes);
        }

        if ($failures < 0) {
            throw InvalidArmStateException::negativeCount('Failures', $failures);
        }
    }

    /**
     * Builds a state from a trial count and the successes among them.
     *
     * @throws InvalidArmStateException
     */
    public static function fromTrials(int $trials, int $successes): self
    {
        if ($trials < 0) {
            throw InvalidArmStateException::negativeCount('Trials', $trials);
        }

        if ($successes < 0) {
            throw InvalidArmStateException::negativeCount('Successes', $successes);
        }

        if ($successes > $trials) {
            throw InvalidArmStateException::moreSuccessesThanTrials($trials, $successes);
        }

        return new self($successes, $trials - $successes);
    }

    /** Records one successful observation on this arm. */
    public function withSuccess(): self
    {
        return new self($this->successes + 1, $this->failures);
    }

    /** Records one failed observation on this arm. */
    public function withFailure(): self
    {
        return new self($this->successes, $this->failures + 1);
    }

    /** Total observations backing this state. */
    public function trials(): int
    {
        return $this->successes + $this->failures;
    }
}
