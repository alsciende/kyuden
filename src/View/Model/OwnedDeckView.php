<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A deck of the authenticated user with its record (`GET /api/self/decks`).
 */
final readonly class OwnedDeckView
{
    public function __construct(
        public DeckView $deck,
        public DeckRecordView $record,
    ) {
    }
}
