<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\TriggerActionAbility;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AbilityService;
use App\Service\OpportunityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in any game place marked `action_window` in the game workflow. The card must be where its abilities can be
 * used (App\Service\AbilityService). Interrupt and reaction abilities are triggered with TriggerInterruptAbility and
 * TriggerReactionAbility, by any player whose triggering condition occurs.
 * The player keeps the opportunity to act: they pay the costs and resolve the effects with other actions, then end
 * their opportunity (EndOpportunity).
 */
#[AsMessageHandler]
final readonly class TriggerActionAbilityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private OpportunityService $opportunityService,
        private AbilityService $abilityService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanTakeAction($game, $player);
    }

    public function __invoke(TriggerActionAbility $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->abilityService->trigger($this->cardRepository->get($message->getCardId(), $game), $player);
    }
}
