<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The database of decks a deck is imported from cannot be reached, or answers what is not a deck.
 */
class DeckSourceUnavailableException extends \RuntimeException
{
}
