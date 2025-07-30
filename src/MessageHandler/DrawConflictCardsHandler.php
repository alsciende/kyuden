<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
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
        $conflictDeck = $player->getCardsByCurrentPlace(ConflictCard::STATE_DRAW_DECK);
        for ($i = 0; $i < $message->getQuantity(); ++$i) {
            $topCard = array_pop($conflictDeck);
            if ($topCard === null) {
                throw new DrawingFromEmptyDeckException('Conflict deck is empty.');
            }
            $this->conflictCardStateMachine->apply(
                $topCard,
                'draw_card'
            );
        }
        $player->resetCardGroups();
    }
}
