<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Game;

/**
 * The game has started, every player has joined: each user finds it on their topic, with their player (e.g. a game
 * the queue has created for them). Not when a player joins a game still waiting for others, which is not ready to be
 * shown.
 */
class GameStartedNotification extends AbstractNotification
{
    public function __construct(Game $game)
    {
        $playerIds = [];
        foreach ($game->getPlayers() as $player) {
            $this->addTopic(Topics::user($player->getUserId()));
            $playerIds[$player->getUserId()] = $player->getId();
        }

        $event = new NotificationEvent('game_started');
        $event->add('id', $game->getId());
        // the player of each user, by user id: the topics of every user get the same update
        $event->add('player_ids', $playerIds);

        $this->setEvent($event);
    }
}
