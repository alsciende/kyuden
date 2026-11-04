<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardOnDeck;

/**
 * Right Hand of the Emperor.
 */
final readonly class RightHandOfTheEmperor27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-right-hand-of-the-emperor';
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
