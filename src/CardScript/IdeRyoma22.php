<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ide Ryōma.
 */
final readonly class IdeRyoma22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-ide-ryoma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
