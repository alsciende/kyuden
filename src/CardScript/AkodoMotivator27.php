<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\DiscardRandomCardFromHand;

/**
 * Akodo Motivator.
 */
final readonly class AkodoMotivator27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-akodo-motivator';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardRandomCardFromHand::class];
    }
}
