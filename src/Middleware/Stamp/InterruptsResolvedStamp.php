<?php

declare(strict_types=1);

namespace App\Middleware\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The message waited in an interrupt window that has closed (InterruptWindowMiddleware): it is handled without asking
 * for interrupts again.
 */
final readonly class InterruptsResolvedStamp implements StampInterface
{
}
