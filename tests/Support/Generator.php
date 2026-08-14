<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Tests\Support;

use CleatSquad\Bandit\ArmState;

/**
 * Seeded generator for property-based tests.
 *
 * Cases are drawn rather than enumerated, but the seed is fixed: a failure is
 * reproducible from the test name alone, and the suite never goes flaky.
 */
final class Generator
{
    /**
     * Magnitudes counts are drawn from. Small counts dominate because that is
     * where posteriors are widest and edge cases live; the large ones keep the
     * arithmetic honest far from the prior.
     *
     * @var list<int>
     */
    private const MAGNITUDES = [0, 1, 2, 5, 20, 100, 5000, 1000000];

    private readonly \Random\Randomizer $randomizer;

    private function __construct(int $seed)
    {
        $this->randomizer = new \Random\Randomizer(new \Random\Engine\Mt19937($seed));
    }

    public static function seeded(int $seed): self
    {
        return new self($seed);
    }

    /** A count biased towards the small end of the range. */
    public function count(): int
    {
        $magnitude = self::MAGNITUDES[$this->randomizer->getInt(0, count(self::MAGNITUDES) - 1)];

        return $magnitude === 0 ? 0 : $this->randomizer->getInt(0, $magnitude);
    }

    public function armState(): ArmState
    {
        return new ArmState($this->count(), $this->count());
    }

    /**
     * An arm set of one to six arms, mixing string and integer keys since the
     * public contract accepts both.
     *
     * @return non-empty-array<string|int, ArmState>
     */
    public function armSet(): array
    {
        $arms = [$this->armKey(0) => $this->armState()];

        for ($i = 1, $size = $this->randomizer->getInt(1, 6); $i < $size; $i++) {
            $arms[$this->armKey($i)] = $this->armState();
        }

        return $arms;
    }

    private function armKey(int $index): string|int
    {
        return $this->randomizer->getInt(0, 1) === 1 ? sprintf('arm_%d', $index) : $index;
    }

    public function seed(): int
    {
        return $this->randomizer->getInt(1, 1000000);
    }

    /** A probability in (0, 1), for simulating an arm's true conversion rate. */
    public function probability(): float
    {
        return $this->randomizer->getInt(1, 999) / 1000.0;
    }
}
