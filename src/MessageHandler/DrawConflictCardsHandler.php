<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\StackType;
use App\Exception\Rules\DrawingFromEmptyDeckException;
use App\Message\DrawConflictCards;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final readonly class DrawConflictCardsHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    public function __invoke(DrawConflictCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $quantity = $message->getQuantity();
        $cards = $player->getUniqueStack(StackType::ConflictDrawDeck)->getTopCards($quantity);
        if (count($cards) < $quantity) {
            throw new DrawingFromEmptyDeckException('Conflict deck is empty.');
        }

        foreach ($cards as $card) {
            $this->conflictCardStateMachine->apply(
                $card,
                'draw_card'
            );
        }
    }
}
