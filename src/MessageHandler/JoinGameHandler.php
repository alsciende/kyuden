<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Deck;
use App\Entity\Player;
use App\Exception\Data\DeckNotFoundException;
use App\Message\JoinGame;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JoinGameHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(JoinGame $message): Player
    {
        $game = $this->gameRepository->get($message->getGameId(), 'join_game');

        $player = new Player($message->getPlayerId());
        $player->setUserId($message->getUserId());
        $deck = $this->entityManager->find(Deck::class, $message->getDeckId());
        if (! $deck instanceof Deck) {
            throw new DeckNotFoundException($message->getDeckId());
        }
        $player->setDeck($deck);
        $game->addPlayer($player);

        $this->logger->info("Player {$player->getId()} has joined game {$game->getId()}.");

        return $player;
    }
}
