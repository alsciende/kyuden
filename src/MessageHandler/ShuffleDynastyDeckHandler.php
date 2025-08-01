<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Message\ShuffleDynastyDeck;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DeckShuffler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShuffleDynastyDeckHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private DeckShuffler $shuffler,
    ) {
    }

    public function __invoke(ShuffleDynastyDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->shuffler->shuffleCards($player->getCardsByCurrentPlace(DynastyCard::STATE_DRAW_DECK)->getValues());
    }
}
