<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Enum\AbilityTiming;

/**
 * Rising Stars Kata.
 */
final readonly class RisingStarsKata22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-rising-stars-kata';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }
}
