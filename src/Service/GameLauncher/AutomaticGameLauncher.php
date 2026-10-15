<?php

declare(strict_types=1);

namespace App\Service\GameLauncher;

use App\Entity\Candidate;
use App\Message\CreateGame;
use App\Message\JoinGame;
use App\MessageHandler\CreateGameHandler;
use App\MessageHandler\JoinGameHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Launches Games and automatically adds Players to it.
 */
readonly class AutomaticGameLauncher implements GameLauncherInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CreateGameHandler $createGameHandler,
        private JoinGameHandler $joinGameHandler,
    ) {
    }

    /**
     * @param array<Candidate> $candidates
     */
    #[\Override]
    public function createGame(array $candidates): void
    {
        $gameId = Uuid::v4()->toString();
        $createGame = CreateGame::withRandomSeed($gameId);
        ($this->createGameHandler)($createGame);

        foreach ($candidates as $candidate) {
            $playerId = Uuid::v4()->toString();
            $joinGame = new JoinGame(
                $gameId,
                $playerId,
                $candidate->getUser()->getId(),
                $candidate->getDeck()->getId(),
            );
            ($this->joinGameHandler)($joinGame);

            // candidate has joined, we must remove it
            $this->entityManager->remove($candidate);
        }
    }
}
