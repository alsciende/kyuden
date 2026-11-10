<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\EndResolution;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AttackedProvinceService;
use App\Service\OpportunityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Not tied to a step: an interrupt or a reaction ends in any place, during a duel too; an action ends the
 * opportunity to act of the player in any step resolved in player order (App\Service\OpportunityService).
 */
#[AsMessageHandler]
final readonly class EndResolutionHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private OpportunityService $opportunityService,
        private AttackedProvinceService $attackedProvinceService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanEndResolution($game, $player);
        // last, so that AwaitedPlayersResolver tells a player waited for to reveal it
        $this->attackedProvinceService->assertRevealed($game);
    }

    public function __invoke(EndResolution $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->opportunityService->endResolution($game, $player);
    }
}
