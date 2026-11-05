<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\AbilityTiming;
use App\Message\PlayInterruptEvent;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\PlayService;
use App\Service\OpportunityService;
use App\State\Model\Resolution;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place, during a duel too, and from any player of the game, whether or not they have the
 * opportunity to act: interrupts are triggered by their triggering condition, which the engine does not know.
 * Neither the presence of an interrupt ability on the event nor its triggering condition is checked. The
 * opportunities to act do not change: the player ends the interrupt once resolved (EndResolution).
 */
#[AsMessageHandler]
final readonly class PlayInterruptEventHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private PlayService $playService,
        private OpportunityService $opportunityService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PlayInterruptEvent $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->playService->playEvent($card, $player, $message->getCardCost(), AbilityTiming::Interrupt);

        $this->opportunityService->startInterruptOrReaction($player, new Resolution($card->getId(), AbilityTiming::Interrupt));
    }
}
