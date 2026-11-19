<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A registration of the authenticated user in a tournament (`/api/self/tournaments`), with its deck.
 */
final readonly class TournamentEntryView
{
    public function __construct(
        public TournamentSummaryView $tournament,
        public string $deckId,
        /** the name of the deck, null for a deck without one */
        public ?string $deckName,
        public \DateTimeImmutable $registeredAt,
        /** whether they have lost a match of the tournament */
        public bool $eliminated,
    ) {
    }
}
