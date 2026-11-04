<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardOnDeck;

/**
 * Kaiu Siege Force.
 */
final readonly class KaiuSiegeForce26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kaiu-siege-force';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
