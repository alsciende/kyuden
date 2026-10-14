<?php

declare(strict_types=1);

namespace App\Attribute;

/**
 * Marks a message as an action of the game, discoverable by ActionMessageRegistry.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class AppAction
{
    public function __construct(
        /**
         * Whether players can send it through the API: false for the actions of the platform (game creation…).
         */
        public bool $public = true,
    ) {
    }
}
