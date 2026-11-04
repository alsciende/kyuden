<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Setting the Standard.
 */
final readonly class SettingTheStandard41 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '41-setting-the-standard';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }
}
