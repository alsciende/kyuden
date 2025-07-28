<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Exception\Data\GameNotFoundException;
use App\Factory\CardFactory;
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
        private CardFactory $factory,
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
            throw new GameNotFoundException($message->getDeckId());
        }
        $player->setDeck($deck);
        $game->addPlayer($player);

        foreach ($message->getCardIds() as $cardId => $id) {
            $this->addCard($game, $player, $cardId, $id);
        }

        $this->logger->info("Player {$player->getId()} has joined game {$game->getId()}.");

        return $player;
    }

    private function addCard(Game $game, Player $player, string $physicalCardId, string $logicalCardId): PhysicalCard
    {
        $card = $this->factory->createCard($physicalCardId, $logicalCardId);

        $game->addCard($card);
        $player->addCard($card);

        return $card;
    }
}
