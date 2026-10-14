<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The game has changed since the version the sender expected (`If-Match`): the message is not handled.
 */
class StaleVersionException extends \RuntimeException
{
    public function __construct(int $expectedVersion, int $currentVersion)
    {
        parent::__construct("The game is at version {$currentVersion}, not {$expectedVersion}.");
    }
}
