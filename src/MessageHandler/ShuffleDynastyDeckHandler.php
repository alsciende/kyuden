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
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $player->getUniqueStack(StackType::DynastyDrawDeck)->shuffle();
    }
}
