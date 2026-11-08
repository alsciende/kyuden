<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\RevealCards;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\CardInspector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on the deck, the hand or the provinces of any player: the engine does
 * not apply card effects, which decide who reveals what.
 */
#[AsMessageHandler]
final readonly class RevealCardsHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardInspector $cardInspector,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(RevealCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->cardInspector->revealPile($player, $target, $message->getStack(), $message->getQuantity());
    }
}
