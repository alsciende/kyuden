<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Event\GameCreatedEvent;
use App\Message\CreateGame;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class CreateGameHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('game')]
        private WorkflowInterface $gameStateMachine,
        private EventDispatcherInterface $eventDispatcher,
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(CreateGame $message): Game
    {
        $game = new Game($message->getGameId(), hex2bin($message->getSeed()) ?: throw new \InvalidArgumentException('The seed must be hex encoded.'), $message->isFriendly());
        $this->gameStateMachine->getMarking($game);
        $this->stateManager->withState($game, function (GameState $state) use ($message): GameState {
            $state->mulligan = $message->hasMulligan();

            return $state;
        });
        $this->entityManager->persist($game);
        $this->eventDispatcher->dispatch(new GameCreatedEvent($game));

        return $game;
    }
}
