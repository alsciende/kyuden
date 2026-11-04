<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Asako Azunami.
 */
final readonly class AsakoAzunami12 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '12-asako-azunami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
