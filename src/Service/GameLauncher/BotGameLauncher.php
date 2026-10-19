<?php

declare(strict_types=1);

namespace App\Service\GameLauncher;

use App\Entity\Deck;
use App\Entity\User;
use App\Message\CreateGame;
use App\Message\JoinGame;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Launches a game between a user and a bot, without the queue, through the bus like AutomaticGameLauncher: the user
 * joins first, then the bot, and the game starts.
 */
readonly class BotGameLauncher
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @return array{string, string} the id of the game and the id of the player of the user
     */
    public function launch(User $user, Deck $deck, User $bot, Deck $botDeck): array
    {
        $gameId = Uuid::v4()->toString();
        $playerId = Uuid::v4()->toString();
        $this->bus->dispatch(CreateGame::withRandomSeed($gameId));
        $this->bus->dispatch(new JoinGame($gameId, $playerId, $user->getId(), $deck->getId()));
        $this->bus->dispatch(new JoinGame($gameId, Uuid::v4()->toString(), $bot->getId(), $botDeck->getId()));

        return [$gameId, $playerId];
    }
}
