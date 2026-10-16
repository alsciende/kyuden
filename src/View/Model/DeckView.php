<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A deck a user can join the queue with: their own decks, and the decks without owner (the starter decks).
 */
final readonly class DeckView
{
    /**
     * @param array<string, int> $cards quantity by card code
     */
    public function __construct(
        public string $id,
        public ?string $name,
        /** the user who created the deck, null for the decks every user can use */
        public ?string $ownerId,
        public array $cards,
    ) {
    }
}
