<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Message\ShuffleIntoDeck;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ProvinceRefiller;
use App\Service\StackShuffler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Accepted in any game place and from any player of the game, on any card: the engine does not apply card effects,
 * which decide what goes into a deck. The `shuffle_into_deck` transition of the conflict and dynasty card workflows
 * fails for the other cards (provinces, strongholds, roles). The deck is shuffled, then a province the dynasty card
 * has left is refilled from it (ProvinceRefiller).
 */
#[AsMessageHandler]
final readonly class ShuffleIntoDeckHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
        private StackShuffler $stackShuffler,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(ShuffleIntoDeck $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);

        $stack = $card->getStack();
        $this->workflowRegistry->get($card)->apply($card, 'shuffle_into_deck');
        // the card is created again with a new id: only its deck is kept
        $this->stackShuffler->shuffle($card->getStack());
        if ($card instanceof DynastyCard) {
            $this->provinceRefiller->refillIfEmpty($stack);
        }
    }
}
