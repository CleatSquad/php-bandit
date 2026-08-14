<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests\Support;

/**
 * A random engine that always returns the same bytes.
 *
 * Two arms in the same state then draw the exact same sample, which is the only
 * way to observe how a tie is broken — with real randomness, two floats are
 * never equal. The value is small enough that `Randomizer::getInt()` accepts it
 * without retrying, and that Marsaglia & Tsang's rejection loop accepts on its
 * first pass; a constant the loop rejected would never terminate.
 */
final class ConstantEngine implements \Random\Engine
{
    public function generate(): string
    {
        return "\x34\x12\x00\x00\x00\x00\x00\x00";
    }
}
