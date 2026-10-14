<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\StackType;
use App\Message\ShuffleConflictDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\StackShuffler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShuffleConflictDeckHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private StackShuffler $stackShuffler,
    ) {
    }

    public function __invoke(ShuffleConflictDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        $this->stackShuffler->shuffle($player->getUniqueStack(StackType::ConflictDrawDeck));
    }
}
