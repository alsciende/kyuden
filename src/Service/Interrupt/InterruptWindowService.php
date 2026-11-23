<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Element;
use App\Exception\Rules\InterruptWindowOpenException;
use App\Exception\Rules\NoInterruptWindowException;
use App\Exception\Rules\NotAwaitedInInterruptWindowException;
use App\Exception\Rules\ResolutionInProgressException;
use App\Message\BidHonor;
use App\Message\CancelDuel;
use App\Message\CancelImminentTrigger;
use App\Message\DeclareDuelStatistic;
use App\Message\EndDuel;
use App\Message\ClaimAbandonment;
use App\Message\ConcedeGame;
use App\Message\EndAbandonedGame;
use App\Message\EndResolution;
use App\Message\PassInterrupt;
use App\Message\PassOpportunity;
use App\Message\PlayInterruptEvent;
use App\Message\PlayReactionEvent;
use App\Message\TriggerInterruptAbility;
use App\Message\TriggerReactionAbility;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\InterruptWindow;

/**
 * The interrupt windows of a game (GameState::interruptWindows): only the last one opened is answered.
 *
 * While one is open, the game waits for it: the engine accepts the interrupts, the reactions (to what the interrupts
 * do), passing in the window, cancelling a condition, conceding, the effects of a player who resolves an interrupt
 * initiated since the window opened, and the steps of a duel such an interrupt has initiated; it refuses the rest
 * (InterruptWindowOpenException), so that what waits cannot become invalid meanwhile.
 */
final readonly class InterruptWindowService
{
    /**
     * Accepted from any player while a window is open.
     */
    private const array ACCEPTED = [
        PassInterrupt::class,
        CancelImminentTrigger::class,
        PlayInterruptEvent::class,
        TriggerInterruptAbility::class,
        PlayReactionEvent::class,
        TriggerReactionAbility::class,
        ConcedeGame::class,
        ClaimAbandonment::class,
        EndAbandonedGame::class,
    ];

    /**
     * Accepted from any player while a duel is being resolved: a duel an interrupt has initiated in the window
     * (Defend Your Honor) involves the opponent.
     */
    private const array DUEL = [
        BidHonor::class,
        DeclareDuelStatistic::class,
        EndDuel::class,
        CancelDuel::class,
    ];

    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * The window answered now, the last one opened.
     */
    public function current(Game $game): ?InterruptWindow
    {
        return $this->gameStateManager->getState($game)->currentInterruptWindow();
    }

    /**
     * @param class-string $messageClass
     *
     * @throws InterruptWindowOpenException
     */
    public function assertAccepted(Game $game, ?Player $player, string $messageClass): void
    {
        $window = $this->current($game);
        if (! $window instanceof InterruptWindow || in_array($messageClass, self::ACCEPTED, true)) {
            return;
        }

        if (in_array($messageClass, self::DUEL, true) && $this->gameStateManager->getState($game)->duel !== null) {
            return;
        }

        // the effects of an interrupt initiated in the window, which they end (not passing their opportunity)
        if ($player instanceof Player && $messageClass !== PassOpportunity::class && $this->isResolvingInterrupt($window, $player)) {
            return;
        }

        throw new InterruptWindowOpenException();
    }

    /**
     * Whether the player resolves an interrupt (or a reaction) initiated since the window opened.
     */
    public function isResolvingInterrupt(InterruptWindow $window, Player $player): bool
    {
        return $this->playerStateManager->getState($player)->countInterruptsAndReactions() > ($window->resolutionBase[$player->getId()] ?? 0);
    }

    /**
     * @throws NoInterruptWindowException|NotAwaitedInInterruptWindowException|ResolutionInProgressException
     */
    public function assertCanPass(Game $game, Player $player): void
    {
        $window = $this->current($game);
        if (! $window instanceof InterruptWindow) {
            throw new NoInterruptWindowException();
        }

        if (! in_array($player->getId(), $window->awaitedPlayerIds(), true)) {
            throw new NotAwaitedInInterruptWindowException();
        }

        if ($this->isResolvingInterrupt($window, $player)) {
            throw new ResolutionInProgressException();
        }
    }

    public function pass(Game $game, Player $player): void
    {
        $this->gameStateManager->withState($game, function (GameState $state) use ($player): GameState {
            $window = $state->currentInterruptWindow();
            \assert($window instanceof InterruptWindow);
            $window->passedPlayerIds[] = $player->getId();

            return $state;
        });
    }

    /**
     * The player has initiated an interrupt: the passes of the window are cancelled (every player asked answers again
     * once it is resolved), and the card leaves the offers of the player.
     */
    public function interruptInitiated(Game $game, Player $player, string $cardId): void
    {
        if (! $this->current($game) instanceof InterruptWindow) {
            return;
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($player, $cardId): GameState {
            $window = $state->currentInterruptWindow();
            \assert($window instanceof InterruptWindow);
            $window->passedPlayerIds = [];
            $stage = $window->currentStage();
            if ($stage !== null) {
                $playerId = $player->getId();
                $stage->cardIds[$playerId] = array_values(array_diff($stage->cardIds[$playerId] ?? [], [$cardId]));
                $stage->forcedCardIds[$playerId] = array_values(array_diff($stage->forcedCardIds[$playerId] ?? [], [$cardId]));
            }

            return $state;
        });
    }

    /**
     * Cancels the imminent conditions of the card (its param `card`, and those it causes, `host`), of the ring (its
     * param `ring`), or every condition of the window: the window closes once none is left.
     *
     * @throws NoInterruptWindowException
     */
    public function cancel(Game $game, ?string $cardId, ?Element $ring = null): void
    {
        if (! $this->current($game) instanceof InterruptWindow) {
            throw new NoInterruptWindowException();
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($cardId, $ring): GameState {
            $window = $state->currentInterruptWindow();
            \assert($window instanceof InterruptWindow);
            $kept = [];
            foreach ($window->triggers as $trigger) {
                $card = $trigger->params['card'] ?? null;
                $triggerRing = $trigger->params['ring'] ?? null;
                $named = match (true) {
                    $cardId !== null => $card === $cardId || ($trigger->params['host'] ?? null) === $cardId,
                    $ring instanceof Element => $triggerRing === $ring->value,
                    default => true,
                };
                if (! $named) {
                    $kept[] = $trigger;
                } elseif ($card !== null && $trigger->resolvedWhenCancelled) {
                    $window->cancelledEffectCardIds[] = $card;
                } elseif ($card !== null) {
                    $window->cancelledCardIds[] = $card;
                } elseif ($triggerRing !== null) {
                    $window->cancelledRings[] = $triggerRing;
                }
            }

            $window->triggers = $kept;
            if ($kept === []) {
                $window->stages = [];
            }

            return $state;
        });
    }

    /**
     * Moves the window on as far as it can: a stage every player asked has passed, nobody resolving an interrupt
     * initiated in the window, gives way to the next one; the window closes after its last stage, or once no condition
     * is left, and is removed from the state. What waits in it is then done by the caller.
     */
    public function closeIfDone(Game $game): ?InterruptWindow
    {
        $window = $this->current($game);
        if (! $window instanceof InterruptWindow) {
            return null;
        }

        if ($window->stages !== []) {
            foreach ($this->gameStateManager->getState($game)->playerIds as $playerId) {
                if ($this->isResolvingInterrupt($window, $game->getPlayer($playerId))) {
                    return null;
                }
            }

            if ($window->awaitedPlayerIds() !== []) {
                return null;
            }
        }

        $closed = null;
        $this->gameStateManager->withState($game, function (GameState $state) use (&$closed): GameState {
            $window = $state->currentInterruptWindow();
            \assert($window instanceof InterruptWindow);
            array_shift($window->stages);
            $window->passedPlayerIds = [];
            if ($window->stages === [] || $window->triggers === []) {
                $closed = array_pop($state->interruptWindows);
            }

            return $state;
        });

        return $closed;
    }

    /**
     * The players the window waits for: those of its current stage who have not passed, and those resolving an
     * interrupt initiated in it.
     *
     * @return list<string>
     */
    public function awaitedPlayerIds(Game $game): array
    {
        $window = $this->current($game);
        if (! $window instanceof InterruptWindow) {
            return [];
        }

        $awaited = $window->awaitedPlayerIds();
        foreach ($this->gameStateManager->getState($game)->playerIds as $playerId) {
            if (! in_array($playerId, $awaited, true) && $this->isResolvingInterrupt($window, $game->getPlayer($playerId))) {
                $awaited[] = $playerId;
            }
        }

        return $awaited;
    }
}
