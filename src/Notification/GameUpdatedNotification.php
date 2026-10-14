<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Game;

/**
 * The state of the game has changed: its players read it again. Carries no data of the game, so that nothing secret
 * is published.
 */
class GameUpdatedNotification extends AbstractNotification
{
    public function __construct(
        private readonly Game $game,
    ) {
        $this->addTopic('/games/' . $this->game->getId());

        $event = new NotificationEvent('game_updated');
        $event->add('id', $this->game->getId());
        $event->add('version', $this->game->getVersion());

        $this->setEvent($event);
    }
}
