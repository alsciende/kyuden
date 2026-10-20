<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\TriggerReactionAbility;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AbilityService;
use App\Service\OpportunityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place, during a duel too, and from any player of the game, whether or not they have the
 * opportunity to act: reactions are triggered by their triggering condition, which the engine does not know. The card
 * must be where its abilities can be used (App\Service\AbilityService). The opportunities to act do not change: the
 * player ends the reaction once resolved (EndResolution).
 */
#[AsMessageHandler]
final readonly class TriggerReactionAbilityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private AbilityService $abilityService,
        private OpportunityService $opportunityService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TriggerReactionAbility $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->abilityService->trigger($this->cardRepository->get($message->getCardId(), $game), $player);

        $this->opportunityService->startInterruptOrReaction($player);
    }
}
