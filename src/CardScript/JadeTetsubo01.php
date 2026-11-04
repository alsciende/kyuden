<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Jade Tetsubō.
 */
final readonly class JadeTetsubo01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-jade-tetsubo';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
