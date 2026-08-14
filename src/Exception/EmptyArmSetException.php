<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Exception;

/** Thrown when a decision is requested without any candidate arm. */
final class EmptyArmSetException extends \InvalidArgumentException implements BanditException
{
    public static function create(): self
    {
        return new self('Cannot select an arm from an empty set.');
    }
}
