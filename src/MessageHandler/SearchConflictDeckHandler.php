<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Message\SearchConflictDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\CardInspector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on the conflict deck of any player: the engine does not
 * apply card effects, which decide who searches what.
 */
#[AsMessageHandler]
final readonly class SearchConflictDeckHandler implements PlayerActionHandlerInterface
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

    public function __invoke(SearchConflictDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->cardInspector->searchDeck($player, $target, StackType::ConflictDrawDeck);
    }
}
