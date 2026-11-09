<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * An action of the player who follows a replay (ReplayView): its version in the original game, whose log tells what it
 * did, and the type of its message.
 */
final readonly class ReplayActionView
{
    public function __construct(
        public int $version,
        public string $type,
    ) {
    }
}
