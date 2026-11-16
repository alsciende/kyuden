<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The sender searches the whole dynasty deck of a player (glossary, Search), e.g. to resolve an effect ("search your
 * dynasty deck for…": Emperor's Summons, Favorable Dealbroker, Kaiu Shihobu, Daughter of War): they look at every card of it, which the log gives to them only, the top card
 * first (the others read how many cards), then resolve the effect with other actions on them (`add_card_to_hand`,
 * `put_character_into_play`, `place_card_under`, `reveal_card`…), and shuffle the deck (`shuffle_dynasty_deck`), as an
 * entire deck searched must be. To search the top cards of a deck only, `look_at_cards` with a quantity. Nothing is
 * recorded: the cards are out of sight again afterwards.
 */
#[AppAction(special: true)]
final readonly class SearchDynastyDeck implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player whose dynasty deck it is.')]
        private string $targetPlayerId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }
}
