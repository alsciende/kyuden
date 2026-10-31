<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A stack of a player whose cards some players do not see, which an effect looks at or reveals: a deck (from its top)
 * or a hand.
 */
enum HiddenPile: string
{
    case ConflictDeck = 'conflict_deck';
    case DynastyDeck = 'dynasty_deck';
    case Hand = 'hand';

    public function stackType(): StackType
    {
        return match ($this) {
            self::ConflictDeck => StackType::ConflictDrawDeck,
            self::DynastyDeck => StackType::DynastyDrawDeck,
            self::Hand => StackType::Hand,
        };
    }
}
