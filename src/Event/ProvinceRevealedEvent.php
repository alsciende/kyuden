<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\Player;

/**
 * A facedown province has been turned face up (transition `reveal`, whatever made it: RevealProvince). Not logged: the
 * transition writes its entry.
 */
final readonly class ProvinceRevealedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public ProvinceCard $province,
        public Player $controller,
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
        return 'province_revealed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'province' => $this->province->getId(),
            'controller' => $this->controller->getId(),
        ];
    }
}
