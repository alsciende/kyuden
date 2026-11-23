<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * A character in play would be honored (HonorCharacter). Cancelled ("receives the honor instead": Young Rumormonger),
 * it is not.
 */
final readonly class CharacterHonoringTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * The player who controls it.
         */
        public Player $controller,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'character_honoring';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId(), 'controller' => $this->controller->getId()];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{card} would be honored';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['card' => $this->card];
    }
}
