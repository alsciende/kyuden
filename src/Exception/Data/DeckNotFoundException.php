<?php

declare(strict_types=1);

namespace App\Exception\Data;

class DeckNotFoundException extends NotFoundException
{
    public function __construct(
        private readonly string $deckId,
    ) {
        parent::__construct("Deck not found: [{$this->deckId}]");
    }

    public function getDeckId(): string
    {
        return $this->deckId;
    }
}
