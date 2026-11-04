<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveAttachment;
use App\Message\MoveFate;

/**
 * Karada District.
 */
final readonly class KaradaDistrict02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-karada-district';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class, MoveFate::class];
    }
}
