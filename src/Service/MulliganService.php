<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Event\CardsMulliganedEvent;
use App\Event\MulliganDeclinedEvent;
use App\Exception\Rules\NotActivePlayerException;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Registry;

/**
 * The mulligans of setup (glossary, Mulligan: MulliganDynastyCards, MulliganConflictCards): in player order, starting
 * with the first player, each player has one opportunity to mulligan any number of cards, which are replaced from the
 * top of their deck, then shuffled back into it. The step ends once every player has had their opportunity
 * (MulliganEndedGuardListener).
 */
final readonly class MulliganService
{
    public function __construct(
        private GameStateManager $stateManager,
        private Registry $workflowRegistry,
        private StackShuffler $stackShuffler,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The mulligan step of the place, and the opportunity of the player.
     */
    public function assertAvailable(Game $game, Player $player, string $place): void
    {
        $game->assertPlace($place);
        if (! $this->stateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    /**
     * The cards set aside, already replaced, are shuffled back into the deck; the next player gets their opportunity.
     *
     * @param list<PhysicalCard> $cards
     */
    public function shuffleBack(Player $player, array $cards, Stack $deck): void
    {
        $game = $player->getGame();
        if ($cards === []) {
            $this->eventDispatcher->dispatch(new MulliganDeclinedEvent($game, $player));
        } else {
            foreach ($cards as $card) {
                $this->workflowRegistry->get($card)->apply($card, 'shuffle_into_deck');
            }

            // the cards of the deck are created again with new ids
            $this->stackShuffler->shuffle($deck);
            $this->eventDispatcher->dispatch(new CardsMulliganedEvent($game, $player, count($cards), $deck));
        }

        $this->stateManager->withState($game, function (GameState $state) use ($player): GameState {
            $next = $state->getNextPlayerId($player);
            // the first player has had their opportunity already: the step is over
            if ($next === $state->firstPlayerId) {
                return $state->deactivatePlayer($player);
            }

            $state->activePlayerIds = [$next];

            return $state;
        });
    }
}
