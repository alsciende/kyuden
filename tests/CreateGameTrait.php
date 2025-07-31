<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Game;
use App\Message\CreateGame;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

trait CreateGameTrait
{
    private MessageBusInterface $messageBus;
    private GameRepository $gameRepository;

    public function createGame(): ?Game
    {
        $gameId = Uuid::v4()->toString();
        $message = new CreateGame($gameId);
        $this->messageBus->dispatch($message);

        return $this->gameRepository->find($gameId);
    }
}
