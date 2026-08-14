<?php

declare(strict_types=1);

namespace CleatSquad\Bandit\Exception;

/**
 * Marker implemented by every exception this package throws.
 * Each one also extends its natural SPL class, so existing catches keep working.
 */
interface BanditException extends \Throwable
{
}
