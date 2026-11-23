<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;

/**
 * The first player would pass the first player token to the next player (`first_player_token_passed`, 5.8).
 * Cancelled (Way of the Unicorn: "keep it instead"), they keep it.
 */
final readonly class FirstPlayerTokenPassingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $player,
        public Player $next,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'first_player_token_passing';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId(), 'next' => $this->next->getId()];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{player} would pass the first player token';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player];
    }
}
