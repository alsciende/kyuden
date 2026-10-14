<?php

declare(strict_types=1);

namespace App\Middleware\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The version of the game the sender saw when sending the message: the message is rejected if the game has changed
 * since (GameVersionMiddleware). Not part of the message, so that replaying an event never fails on it.
 */
final readonly class ExpectedVersionStamp implements StampInterface
{
    public function __construct(
        public int $version,
    ) {
    }
}
