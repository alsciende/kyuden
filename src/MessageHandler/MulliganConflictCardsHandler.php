<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Exception\Rules\NotInHandException;
use App\Message\MulliganConflictCards;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DrawDeckService;
use App\Service\MulliganService;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Setup 7: the active player mulligans cards of their starting hand. As many cards are drawn from the top of the
 * conflict deck first, then the cards chosen are shuffled back into it (as if set aside meanwhile).
 */
#[AsMessageHandler]
final readonly class MulliganConflictCardsHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private MulliganService $mulliganService,
        private DrawDeckService $drawDeckService,
        #[Target('conflict_card')]
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->mulliganService->assertAvailable($game, $player, 'conflict_mulligan');
    }

    public function __invoke(MulliganConflictCards $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $cards = array_map(
            fn (string $cardId): ConflictCard => $this->assertInHand($this->cardRepository->get($cardId, $game), $player),
            $message->getCardIds(),
        );

        for ($i = 0, $count = count($cards); $i < $count; ++$i) {
            $card = $this->drawDeckService->getTopCard($player, StackType::ConflictDrawDeck);
            if ($card instanceof ConflictCard) {
                $this->conflictCardStateMachine->apply($card, 'draw_card');
            }
        }

        $this->mulliganService->shuffleBack($player, $cards, $player->getUniqueStack(StackType::ConflictDrawDeck));
    }

    private function assertInHand(PhysicalCard $card, Player $player): ConflictCard
    {
        if (! $card instanceof ConflictCard || $card->getStack()->getType() !== StackType::Hand || $card->getPlayer() !== $player) {
            throw new NotInHandException($card, $player);
        }

        return $card;
    }
}
