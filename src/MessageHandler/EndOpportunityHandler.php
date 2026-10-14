<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\EndOpportunity;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Not tied to a step: any step resolved in player order gives the opportunity to act this way.
 */
#[AsMessageHandler]
final readonly class EndOpportunityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private OpportunityService $opportunityService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertNoDuel($game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(EndOpportunity $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->opportunityService->passToNextPlayer($game, $player);
    }
}
