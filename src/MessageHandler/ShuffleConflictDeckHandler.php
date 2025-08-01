<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Message\ShuffleConflictDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DeckShuffler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShuffleConflictDeckHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private DeckShuffler $shuffler,
    ) {
    }

    public function __invoke(ShuffleConflictDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->shuffler->shuffleCards($player->getCardsByCurrentPlace(ConflictCard::STATE_DRAW_DECK)->getValues());
    }
}
