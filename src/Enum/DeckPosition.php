<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a card is put into its deck.
 */
enum DeckPosition: string
{
    case Top = 'top';
    case Bottom = 'bottom';

    /**
     * The transition of the card workflows putting a card there.
     */
    public function transition(): string
    {
        return match ($this) {
            self::Top => 'put_on_top',
            self::Bottom => 'put_at_bottom',
        };
    }
}
