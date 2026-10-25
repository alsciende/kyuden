<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\StackType;
use App\Message\DrawConflictCards;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DrawDeckService;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Draws the cards one by one: an empty deck is replaced by the shuffled discard pile (DrawDeckService). When the
 * discard pile is empty too, the player draws as many cards as possible.
 */
#[AsMessageHandler]
final readonly class DrawConflictCardsHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        #[Target('conflict_card')]
        private WorkflowInterface $conflictCardStateMachine,
        private DrawDeckService $drawDeckService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(DrawConflictCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        for ($i = 0; $i < $message->getQuantity(); ++$i) {
            $card = $this->drawDeckService->getTopCard($player, StackType::ConflictDrawDeck);
            if ($card === null) {
                return;
            }

            $this->conflictCardStateMachine->apply($card, 'draw_card');
        }
    }
}
