<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Game;

/**
 * The state of the game has changed: its players read it again. Carries no data of the game, so that nothing secret
 * is published. Published on the topic of the game, and on the topic of the game for each of its players (see Topics).
 */
class GameUpdatedNotification extends AbstractNotification
{
    public function __construct(
        private readonly Game $game,
    ) {
        $this->addTopic(Topics::game($this->game->getId()));
        foreach ($this->game->getPlayers() as $player) {
            $this->addTopic(Topics::gameOfUser($player->getUserId(), $this->game->getId()));
        }

        $event = new NotificationEvent('game_updated');
        $event->add('id', $this->game->getId());
        $event->add('version', $this->game->getVersion());

        $this->setEvent($event);
    }
}
