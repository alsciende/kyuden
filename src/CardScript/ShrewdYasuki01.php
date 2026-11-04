<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Shrewd Yasuki.
 */
final readonly class ShrewdYasuki01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-shrewd-yasuki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, PutCardOnDeck::class];
    }
}
