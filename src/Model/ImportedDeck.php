<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Deck;
use App\Enum\DeckImportOutcome;

/**
 * The deck of an import, and what the import did (Service\Deck\DeckImporter).
 */
final readonly class ImportedDeck
{
    public function __construct(
        public Deck $deck,
        public DeckImportOutcome $outcome,
    ) {
    }
}
