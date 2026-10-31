<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Side;
use App\Exception\Rules\WrongCardSideException;
use App\Message\AddCardToHand;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Accepted in any game place and from any player of the game, on any conflict card: the engine does not apply card
 * effects, which decide what is added to a hand. The `add_to_hand` transition fails for a card that is neither in a
 * conflict deck nor in a conflict discard pile.
 */
#[AsMessageHandler]
final readonly class AddCardToHandHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        #[Target('conflict_card')]
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(AddCardToHand $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        // a dynasty card never enters a hand
        if (! $card instanceof ConflictCard) {
            throw new WrongCardSideException($card, Side::Conflict);
        }

        $this->conflictCardStateMachine->apply($card, 'add_to_hand');
    }
}
