<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\AddCardToHand;
use App\Message\RevealCards;

/**
 * Test of Skill.
 */
final readonly class TestOfSkill06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-test-of-skill';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, RevealCards::class];
    }
}
