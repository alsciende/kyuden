<?php

declare(strict_types=1);

namespace App\GameLog;

/**
 * A property of a logged event (GameLog) that only a player knows: the one held by the given property of the event, or
 * nobody when no property is given. For a card, a scalar value or a backed enum. Without it, a card is known to the
 * players who see it where it is (App\View\Visibility), and any other value is public.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class VisibleTo
{
    public function __construct(
        public ?string $player = null,
    ) {
    }
}
