<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\DeckNotFoundException;
use App\Message\JoinGame;
use App\Model\User;
use App\Repository\DeckRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

trait CreatePlayerTrait
{
    private MessageBusInterface $messageBus;
    private PlayerRepository $playerRepository;
    private DeckRepository $deckRepository;

    /**
     * @param non-empty-string $userId
     *
     * @throws ExceptionInterface
     */
    public function createPlayer(Game $game, string $deckId, string $userId): ?Player
    {
        $playerId = Uuid::v4()->toString();
        $deck = $this->deckRepository->find($deckId) ?? throw new DeckNotFoundException($deckId);
        $user = new User($userId);
        $message = new JoinGame(
            $game->getId(),
            $playerId,
            $user->id,
            $user->getUserIdentifier(),
            $deck->getId(),
            $deck->getName(),
            JoinGame::generateCardsIds($deck->getContent()),
        );
        $this->messageBus->dispatch($message);

        return $this->playerRepository->find($playerId);
    }
}
