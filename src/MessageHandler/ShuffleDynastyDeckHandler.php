<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\StackType;
use App\Message\ShuffleDynastyDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShuffleDynastyDeckHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
    ) {
    }

    public function __invoke(ShuffleDynastyDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        $player->getUniqueStack(StackType::DynastyDrawDeck)->shuffle($game->getRandomizer());
    }
}
