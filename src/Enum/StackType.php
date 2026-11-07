<?php

declare(strict_types=1);

namespace App\Enum;

enum StackType: int
{
    case ConflictDrawDeck = 0;
    case ConflictDiscardPile = 1;
    case DynastyDrawDeck = 2;
    case DynastyDiscardPile = 3;
    case StrongholdProvince = 4;
    case Role = 5;
    case Hand = 6;
    case RegularProvince = 7;
    case InPlay = 8;
    // out of play: the cards removed from the game of a player (RemoveFromGame), created when a card is first removed
    case RemovedFromGame = 9;
    // out of play: the cards under a card (PlaceCardUnder), one stack for each card holding some (Stack::getHost())
    case Underneath = 10;

    /**
     * The stacks every player has from the start of the game, one of each type.
     */
    public function isUnique(): bool
    {
        return $this->value < 7;
    }

    /**
     * The out-of-play stacks whose face-down cards the player of the stack knows (Visibility).
     */
    public function isSetAside(): bool
    {
        return $this === self::RemovedFromGame || $this === self::Underneath;
    }
}
