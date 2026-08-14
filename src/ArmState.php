<?php

declare(strict_types=1);

namespace CleatSquad\Bandit;

final readonly class ArmState
{
    public function __construct(
        public int $successes,
        public int $failures,
    ) {
    }
}
