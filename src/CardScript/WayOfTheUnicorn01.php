<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TakeFirstPlayerToken;

/**
 * Way of the Unicorn.
 */
final readonly class WayOfTheUnicorn01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-way-of-the-unicorn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [TakeFirstPlayerToken::class];
    }
}
