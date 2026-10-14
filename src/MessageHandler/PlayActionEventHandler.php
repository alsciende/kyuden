<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Message\PlayActionEvent;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\HandService;
use App\Service\OpportunityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in any game place marked `action_window`, when the player has the opportunity to act and no duel is being
 * resolved, like TriggerActionAbility. Interrupt and reaction events are played with PlayInterruptEvent and
 * PlayReactionEvent, by any player whose triggering condition occurs. Neither the presence of an action ability on the event nor its limits are
 * checked. The player pays its cost and the event goes to the discard pile; the player resolves its effects with other
 * actions, then ends their opportunity (EndOpportunity).
 */
#[AsMessageHandler]
final readonly class PlayActionEventHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private OpportunityService $opportunityService,
        private HandService $handService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanTakeAction($game, $player);
        $this->handService->assertEventInHand($player, AbilityTiming::Action);
    }

    public function __invoke(PlayActionEvent $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->handService->playEvent($this->cardRepository->get($message->getCardId(), $game), $player, $message->getCardCost(), AbilityTiming::Action);
    }
}
