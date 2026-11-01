<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\AlreadyControlledException;
use App\Message\TakeControl;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ControlService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what changes control. The `take_control` transition fails for a card that is not a character or an
 * attachment in play.
 */
#[AsMessageHandler]
final readonly class TakeControlHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ControlService $controlService,
        private Registry $workflowRegistry,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TakeControl $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        if ($this->controlService->getController($card) === $player) {
            throw new AlreadyControlledException($card, $player);
        }

        $this->workflowRegistry->get($card)->apply($card, 'take_control', [
            'controller' => $player,
        ]);
    }
}
