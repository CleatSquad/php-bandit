<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

interface BanditPolicyInterface
{
    public function posteriorMean(int $successes, int $failures): float;

    public function posteriorWeight(int $successes, int $failures): float;

    public function posteriorVariance(int $successes, int $failures): float;

    public function sample(int $successes, int $failures): float;
}
