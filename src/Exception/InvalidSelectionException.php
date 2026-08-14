<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Exception;

/** Thrown when a selection result does not describe a decision that could have happened. */
final class InvalidSelectionException extends \InvalidArgumentException implements BanditException
{
    public static function withoutSamples(): self
    {
        return new self('A selection must carry the draw of at least one arm.');
    }

    public static function unknownArm(string|int $selectedArm): self
    {
        return new self(sprintf('Selected arm "%s" has no draw among the samples.', $selectedArm));
    }

    public static function sampleMismatch(string|int $selectedArm, float $sample, float $drawn): self
    {
        return new self(sprintf(
            'Winning sample %.17g does not match the draw %.17g recorded for arm "%s".',
            $sample,
            $drawn,
            $selectedArm,
        ));
    }
}
