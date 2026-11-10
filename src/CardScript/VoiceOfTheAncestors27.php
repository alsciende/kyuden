<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlayCharacterAsAttachment;

/**
 * Voice of the Ancestors.
 */
final readonly class VoiceOfTheAncestors27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-voice-of-the-ancestors';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlayCharacterAsAttachment::class];
    }
}
