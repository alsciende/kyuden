<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Those Who Serve.
 */
final readonly class ThoseWhoServe27 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '27-those-who-serve';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
