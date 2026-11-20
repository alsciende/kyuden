<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\PersonalHonor;
use App\GameLog\GameLog;

/**
 * The personal honor of a character has changed: honored, dishonored, or back to ordinary. The triggering condition
 * of the cards reacting to a character honored or dishonored (HonorCharacter, DishonorCharacter: a dishonored
 * character honored is ordinary, and has been honored).
 */
#[GameLog('{card} is now {status}')]
final readonly class PersonalHonorChangedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public PersonalHonor $status,
        /**
         * Honored or dishonored (HonorCharacter, DishonorCharacter); null for the rules, the character leaving play.
         */
        public ?PersonalHonor $action = null,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'personal_honor_changed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'action' => $this->action->value ?? 'none',
        ];
    }
}
