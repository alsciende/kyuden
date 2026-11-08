<?php

declare(strict_types=1);

namespace App\Exception\Data;

/**
 * An event of a game could not be replayed: the engine or the data it reads (a deck…) has changed since, or the game
 * was created before its ids were salted.
 */
class ReplayDivergedException extends DataException
{
    public function __construct(
        private readonly int $version,
        ?\Throwable $previous = null,
    ) {
        parent::__construct("The replay diverges at version {$version}" . ($previous instanceof \Throwable ? ": {$previous->getMessage()}" : '.'), 0, $previous);
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
