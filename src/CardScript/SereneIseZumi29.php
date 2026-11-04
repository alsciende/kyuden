<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Serene Ise Zumi.
 */
final readonly class SereneIseZumi29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-serene-ise-zumi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
