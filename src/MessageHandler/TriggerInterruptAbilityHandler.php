<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\TriggerInterruptAbility;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AbilityService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place, during a duel too, and from any player of the game, whether or not they have the
 * opportunity to act: interrupts are triggered by their triggering condition, which the engine does not know. The card
 * must be where its abilities can be used (App\Service\AbilityService). The opportunities to act do not change.
 */
#[AsMessageHandler]
final readonly class TriggerInterruptAbilityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private AbilityService $abilityService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TriggerInterruptAbility $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->abilityService->trigger($this->cardRepository->get($message->getCardId(), $game), $player);
    }
}
