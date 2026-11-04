<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Stolen Secrets.
 */
final readonly class StolenSecrets15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-stolen-secrets';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, PutCardOnDeck::class];
    }
}
