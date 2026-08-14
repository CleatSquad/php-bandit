<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

use CleatSquad\Bandit\Exception\EmptyArmSetException;

/** Decision contract: given candidate arms, choose one. */
interface BanditPolicyInterface
{
    /**
     * Chooses one arm among the candidates.
     *
     * @param array<string|int, ArmState> $arms
     * @throws EmptyArmSetException
     */
    public function select(array $arms): SelectionResult;
}
