<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Message\ShuffleConflictDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\StackShuffler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShuffleConflictDeckHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private StackShuffler $stackShuffler,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(ShuffleConflictDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        $this->stackShuffler->shuffle($player->getUniqueStack(StackType::ConflictDrawDeck));
    }
}
