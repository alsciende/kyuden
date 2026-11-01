<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What importing a deck did: decks with the same cards (Deck::contentHash) are not imported twice for a user.
 */
enum DeckImportOutcome
{
    /** a new deck */
    case Created;

    /** the user had deleted a deck with the same cards: it is theirs again, with the name of the import */
    case Restored;

    /** the user has a deck with the same cards: it is kept as is */
    case AlreadyOwned;
}
