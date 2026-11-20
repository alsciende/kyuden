<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;

/**
 * A province has been broken (transition `break`): by the attacking player at the resolution of a conflict (3.2.5), or
 * by the player whose effect breaks it (BreakProvince). The triggering condition of the cards reacting to a province
 * broken. Not logged: the transition writes its entry.
 */
final readonly class ProvinceBrokenEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public ProvinceCard $province,
        public Player $controller,
        /**
         * The player who broke it.
         */
        public Player $breaker,
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
        return 'province_broken';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'province' => $this->province->getId(),
            'breaker' => $this->breaker->getId(),
        ];
    }
}
