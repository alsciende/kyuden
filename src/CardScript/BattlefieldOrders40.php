<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Battlefield Orders.
 */
final readonly class BattlefieldOrders40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-battlefield-orders';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
