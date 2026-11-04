<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kitsuki Yuikimi.
 */
final readonly class KitsukiYuikimi20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-kitsuki-yuikimi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
