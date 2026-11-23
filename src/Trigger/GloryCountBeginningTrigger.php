<?php

declare(strict_types=1);

namespace App\Trigger;

/**
 * The glory count for the Imperial Favor would begin (`imperial_favor_contest_began`, 3.4). Nothing cancels it.
 */
final readonly class GloryCountBeginningTrigger implements ImminentTriggerInterface
{
    public function __construct(
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'glory_count_beginning';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return 'The glory count would begin';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return [];
    }
}
