<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Game;

/**
 * The state of the game has changed: its players read it again. Carries no data of the game, so that nothing secret
 * is published. Published on the topic of the game, and on the topic of the game for each of its players and spectators
 * (see Topics).
 */
class GameUpdatedNotification extends AbstractNotification
{
    /**
     * @param list<string> $spectatorIds the users watching the game
     */
    public function __construct(
        private readonly Game $game,
        array $spectatorIds = [],
    ) {
        foreach (Topics::ofGame($this->game, $spectatorIds) as $topic) {
            $this->addTopic($topic);
        }

        $event = new NotificationEvent('game_updated');
        $event->add('id', $this->game->getId());
        $event->add('version', $this->game->getVersion());

        $this->setEvent($event);
    }
}
