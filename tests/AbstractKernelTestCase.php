<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Game;
use App\Entity\GameLogEntry;
use App\Entity\Player;
use App\Exception\Data\DeckNotFoundException;
use App\GameLog\GameLogRenderer;
use App\Message\CreateGame;
use App\Message\JoinGame;
use App\Model\User;
use App\Repository\CardRepository;
use App\Repository\DeckRepository;
use App\Repository\GameLogEntryRepository;
use App\Repository\GameRepository;
use App\Repository\LogicalCardRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\Builder\GameBuilder;
use App\View\GameLogViewFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

abstract class AbstractKernelTestCase extends KernelTestCase
{
    protected MessageBusInterface $messageBus;
    protected GameRepository $gameRepository;
    protected CardRepository $cardRepository;
    protected PlayerRepository $playerRepository;
    protected PlayerStateManager $playerStateManager;
    protected DeckRepository $deckRepository;

    #[Before]
    protected function getServices(): void
    {
        $container = static::getContainer();
        // @phpstan-ignore assign.propertyType
        $this->messageBus = $container->get(MessageBusInterface::class);
        // @phpstan-ignore assign.propertyType
        $this->gameRepository = $container->get(GameRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->cardRepository = $container->get(CardRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->playerRepository = $container->get(PlayerRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->playerStateManager = $container->get(PlayerStateManager::class);
        // @phpstan-ignore assign.propertyType
        $this->deckRepository = $container->get(DeckRepository::class);
    }

    /**
     * Starts building a game directly in a given state.
     */
    protected function scenario(): GameBuilder
    {
        return new GameBuilder(
            self::service(EntityManagerInterface::class),
            self::service(LogicalCardRepository::class),
            $this->deckRepository,
            self::service(GameStateManager::class),
            $this->playerStateManager,
            self::service(CardStateManager::class),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        $service = static::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    /**
     * The entries of the log of the game written so far, flushed or not, as the reader sees them (a spectator when
     * null), rendered as text: players by user id (their alias in a scenario), cards by name.
     *
     * @return list<string>
     */
    protected function logLines(Game $game, ?Player $reader = null): array
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $entries = $entityManager->contains($game) ? self::service(GameLogEntryRepository::class)->findSince($game) : [];
        foreach ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof GameLogEntry && $entity->getGame() === $game) {
                $entries[] = $entity;
            }
        }

        usort($entries, fn (GameLogEntry $a, GameLogEntry $b): int => $a->getId() <=> $b->getId());

        return array_map(
            fn (GameLogEntry $entry): string => self::service(GameLogRenderer::class)->render(self::service(GameLogViewFactory::class)->createEntryView($entry, $reader), $game),
            $entries,
        );
    }

    protected function createGame(): ?Game
    {
        $gameId = Uuid::v4()->toString();
        $message = CreateGame::withRandomSeed($gameId);
        $this->messageBus->dispatch($message);

        return $this->gameRepository->find($gameId);
    }

    /**
     * @param non-empty-string $userId
     *
     * @throws ExceptionInterface
     */
    protected function createPlayer(Game $game, string $deckId, string $userId): ?Player
    {
        $playerId = Uuid::v4()->toString();
        $deck = $this->deckRepository->find($deckId) ?? throw new DeckNotFoundException($deckId);
        $user = new User($userId);
        $message = new JoinGame(
            $game->getId(),
            $playerId,
            $user->id,
            $deck->getId(),
        );
        $this->messageBus->dispatch($message);

        return $this->playerRepository->find($playerId);
    }
}
