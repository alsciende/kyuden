<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Kaiu Inventor.
 */
final readonly class KaiuInventor05 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '05-kaiu-inventor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
