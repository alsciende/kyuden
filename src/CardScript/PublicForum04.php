<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Public Forum.
 */
final readonly class PublicForum04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-public-forum';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }
}
