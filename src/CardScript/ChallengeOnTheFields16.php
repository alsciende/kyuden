<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Challenge on the Fields.
 */
final readonly class ChallengeOnTheFields16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-challenge-on-the-fields';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
