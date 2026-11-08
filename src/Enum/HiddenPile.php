<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cards of a player that some players do not see, which an effect looks at or reveals: a deck (from its top), a hand,
 * or the dynasty cards in the provinces (Governor's Spy: the face-down ones are seen by nobody).
 */
enum HiddenPile: string
{
    case ConflictDeck = 'conflict_deck';
    case DynastyDeck = 'dynasty_deck';
    case Hand = 'hand';
    case Provinces = 'provinces';

    public function stackType(): StackType
    {
        return match ($this) {
            self::ConflictDeck => StackType::ConflictDrawDeck,
            self::DynastyDeck => StackType::DynastyDrawDeck,
            self::Hand => StackType::Hand,
            self::Provinces => StackType::RegularProvince,
        };
    }
}
