<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The mon a player shows at the top of their nobori: the ensō, which every player has from the start, or the mon of a
 * Great Clan, earned. Each mon has its own color, drawn by the web client.
 */
enum Mon: string
{
    case Enso = 'enso';
    case Crab = 'crab';
    case Crane = 'crane';
    case Dragon = 'dragon';
    case Lion = 'lion';
    case Phoenix = 'phoenix';
    case Scorpion = 'scorpion';
    case Unicorn = 'unicorn';

    /**
     * The mon of a clan: the ensō for the neutral cards.
     */
    public static function ofClan(Clan $clan): self
    {
        return $clan === Clan::Neutral ? self::Enso : self::from($clan->value);
    }

    /**
     * Every player has it, earned or not.
     */
    public function isGivenToAll(): bool
    {
        return $this === self::Enso;
    }
}
