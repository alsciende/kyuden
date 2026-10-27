<?php

declare(strict_types=1);

namespace App\Service\GameLauncher;

use App\Entity\Candidate;
use App\Entity\Invitation;
use App\Message\CreateGame;
use App\Message\JoinGame;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Launches Games and automatically adds Players to it, through the bus: the messages are recorded as the events of the
 * game, and the game starts once every player has joined (automatic transition `start_game`). The candidates leave the
 * queue once the game is created. A game the candidates are matched in by an invitation is friendly, and uses it.
 */
readonly class AutomaticGameLauncher implements GameLauncherInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
    ) {
    }

    #[\Override]
    public function createGame(array $candidates, ?Invitation $invitation = null): void
    {
        $gameId = Uuid::v4()->toString();
        $this->bus->dispatch(CreateGame::withRandomSeed($gameId, $invitation instanceof Invitation));

        foreach ($candidates as $candidate) {
            $this->bus->dispatch(new JoinGame(
                $gameId,
                Uuid::v4()->toString(),
                $candidate->getUser()->getId(),
                $candidate->getDeck()->getId(),
            ));
        }

        // the candidates have joined, they leave the queue
        foreach ($candidates as $candidate) {
            $this->entityManager->remove($candidate);
        }
        $invitation?->use($gameId);
        $this->entityManager->flush();
    }
}
