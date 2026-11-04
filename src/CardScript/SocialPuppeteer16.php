<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Social Puppeteer.
 */
final readonly class SocialPuppeteer16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-social-puppeteer';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
