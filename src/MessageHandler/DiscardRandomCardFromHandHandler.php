<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\StackType;
use App\Message\DiscardRandomCardFromHand;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on the hand of any player. Nothing happens when the hand
 * is empty.
 */
#[AsMessageHandler]
final readonly class DiscardRandomCardFromHandHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    public function __invoke(DiscardRandomCardFromHand $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $hand = $target->getUniqueStack(StackType::Hand)->getOrderedCards();
        if ($hand === []) {
            return;
        }

        $card = $hand[$game->getRandomizer()->getInt(0, count($hand) - 1)];
        $this->conflictCardStateMachine->apply($card, 'discard_card');
    }
}
