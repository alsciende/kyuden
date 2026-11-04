<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Formal Invitation.
 */
final readonly class FormalInvitation09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-formal-invitation';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
