<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

use CleatSquad\Bandit\Exception\InvalidSelectionException;

/**
 * Outcome of one decision: the arm that won, and the draws it won against.
 * Keys of $samples are the keys of the arm set the decision was made on.
 *
 * The winning draw is not required to be the largest one: a policy is free to
 * pick against its own samples. Only the bookkeeping is enforced — the selected
 * arm was drawn, and $sample is the draw it got.
 */
final readonly class SelectionResult
{
    /**
     * The draw each candidate arm got, keyed as the arm set was.
     *
     * @var non-empty-array<string|int, float>
     */
    public array $samples;

    /**
     * @param array<string|int, float> $samples
     * @throws InvalidSelectionException
     */
    public function __construct(
        public string|int $selectedArm,
        public float $sample,
        array $samples,
    ) {
        if ($samples === []) {
            throw InvalidSelectionException::withoutSamples();
        }

        if (!array_key_exists($selectedArm, $samples)) {
            throw InvalidSelectionException::unknownArm($selectedArm);
        }

        if ($samples[$selectedArm] !== $sample) {
            throw InvalidSelectionException::sampleMismatch($selectedArm, $sample, $samples[$selectedArm]);
        }

        $this->samples = $samples;
    }
}
