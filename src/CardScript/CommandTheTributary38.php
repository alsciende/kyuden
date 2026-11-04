<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Command the Tributary.
 */
final readonly class CommandTheTributary38 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '38-command-the-tributary';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
