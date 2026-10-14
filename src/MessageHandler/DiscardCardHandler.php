<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\DiscardCard;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what is discarded. The `discard_card` transition of the card workflow fails for cards that cannot be
 * discarded (provinces, strongholds, roles) or are already in a discard pile.
 */
#[AsMessageHandler]
final readonly class DiscardCardHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(DiscardCard $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);

        $this->workflowRegistry->get($card)->apply($card, 'discard_card');
    }
}
