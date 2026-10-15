<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\HonorSetEvent;
use App\Exception\Rules\HonorAlreadySetException;
use App\Message\SetHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetHonorHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(SetHonor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
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
