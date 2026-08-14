<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

/**
 * Outcome of one decision: the arm that won, and the draws it won against.
 * Keys of $samples are the keys of the arm set the decision was made on.
 */
final readonly class SelectionResult
{
    /** @param non-empty-array<string|int, float> $samples */
    public function __construct(
        public string|int $selectedArm,
        public float $sample,
        public array $samples,
    ) {
    }
}
