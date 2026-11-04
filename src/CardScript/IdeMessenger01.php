<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Ide Messenger.
 */
final readonly class IdeMessenger01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-ide-messenger';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
