<?php

declare(strict_types=1);

namespace App\GameLog;

/**
 * A card of a logged event (GameLog) that the event reveals to every player, wherever it is: e.g. an event played from
 * the hand, before it goes to the discard pile.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Revealed
{
}
