<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceHonorTokens;
use App\Message\PutCardOnDeck;

/**
 * Endless Archives.
 */
final readonly class EndlessArchives33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-endless-archives';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceHonorTokens::class, PutCardOnDeck::class];
    }
}
