<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Game;

/**
 * A spectator has arrived in a game or left it: its players and spectators read the spectators again. Published like
 * GameUpdatedNotification, on the topic of the game and on the topic of the game for each of its readers.
 */
class SpectatorsUpdatedNotification extends AbstractNotification
{
    /**
     * @param list<string> $spectatorIds the users watching the game, to which the update is also sent
     */
    public function __construct(Game $game, array $spectatorIds)
    {
        foreach (Topics::ofGame($game, $spectatorIds) as $topic) {
            $this->addTopic($topic);
        }

        $event = new NotificationEvent('spectators_updated');
        $event->add('id', $game->getId());

        $this->setEvent($event);
    }
}
