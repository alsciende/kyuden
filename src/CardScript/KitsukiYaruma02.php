<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kitsuki Yaruma.
 */
final readonly class KitsukiYaruma02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-kitsuki-yaruma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }
}
