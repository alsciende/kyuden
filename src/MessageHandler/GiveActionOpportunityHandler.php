<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ActionOpportunitiesGivenEvent;
use App\Message\GiveActionOpportunity;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for any player: the engine does not apply card effects.
 * The opportunities are given by OpportunityService, at the end of the action being resolved or when the next action
 * window opens (GivenActionOpportunityEnterListener).
 */
#[AsMessageHandler]
final readonly class GiveActionOpportunityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private OpportunityService $opportunityService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(GiveActionOpportunity $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->opportunityService->giveActions($game, $target, $message->getActions());
        $this->eventDispatcher->dispatch(new ActionOpportunitiesGivenEvent($game, $target, $message->getActions()));
    }
}
