<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\HonorSetEvent;
use App\Exception\Rules\HonorAlreadySetException;
use App\Message\SetHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sent by the engine during setup (SetupGameTransitionListener): players cannot send it.
 */
#[AsMessageHandler]
final readonly class SetHonorHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SetHonor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->getHonor() !== null) {
                    throw new HonorAlreadySetException();
                }

                return $state->setHonor($message->getHonor());
            },
        );
        $this->eventDispatcher->dispatch(new HonorSetEvent($game, $player, $message->getHonor()));
    }
}
