<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Favored Niece.
 */
final readonly class FavoredNiece01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-favored-niece';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
