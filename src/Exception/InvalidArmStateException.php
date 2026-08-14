<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Exception;

/** Thrown when success or failure counts describe an impossible observation. */
final class InvalidArmStateException extends \InvalidArgumentException implements BanditException
{
    public static function negativeCount(string $name, int $value): self
    {
        return new self(sprintf('%s must be zero or greater, %d given.', $name, $value));
    }

    public static function moreSuccessesThanTrials(int $trials, int $successes): self
    {
        return new self(sprintf('Successes cannot exceed trials, %d of %d given.', $successes, $trials));
    }
}
