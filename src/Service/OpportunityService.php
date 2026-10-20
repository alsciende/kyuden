<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\PassingRule;
use App\Exception\Rules\CannotPlayFromHandException;
use App\Exception\Rules\DuelInProgressException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
use App\Exception\Rules\NothingToResolveException;
use App\Exception\Rules\ResolutionInProgressException;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Opportunity to act in steps with opportunities. In steps resolved in player order, a single player is active and
 * the opportunity goes to the next player in player order who has not passed during the current step. In steps where
 * players act simultaneously, every player is active until they pass. In action windows (PassingRule::Consecutive),
 * a player who acts cancels the passes.
 *
 * A player who acts (a card played, an action ability triggered) resolves their action, then ends it (EndResolution)
 * instead of passing. Interrupts and reactions are resolved the same way, by any player, without using an
 * opportunity to act.
 */
readonly class OpportunityService
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private WorkflowInterface $gameStateMachine,
    ) {
    }

    /**
     * The `opportunities` metadata of the game place, or null when the players have no opportunities to pass.
     */
    public function getPassingRule(Game $game): ?PassingRule
    {
        $place = $game->getCurrentPlace();
        if ($place === null) {
            return null;
        }

        $rule = $this->gameStateMachine->getMetadataStore()->getPlaceMetadata($place)['opportunities'] ?? null;

        return $rule instanceof PassingRule ? $rule : null;
    }

    /**
     * The player has the opportunity to act in an action window (`action_window` metadata of the game place). Playing
     * characters and attachments from hand is allowed in every action window but those marked `play_from_hand: false`.
     *
     * @throws NotInActionWindowException|CannotPlayFromHandException|NotActivePlayerException|DuelInProgressException
     */
    public function assertCanTakeAction(Game $game, Player $player, bool $playFromHand = false): void
    {
        $this->assertNoDuel($game);

        $place = $game->getCurrentPlace();
        $metadata = $place === null ? [] : $this->gameStateMachine->getMetadataStore()->getPlaceMetadata($place);
        if (($metadata['action_window'] ?? false) !== true) {
            throw new NotInActionWindowException($place);
        }

        if ($playFromHand && ($metadata['play_from_hand'] ?? true) !== true) {
            throw new CannotPlayFromHandException($place);
        }

        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    /**
     * No action window opens during a duel, and the step cannot end before the duel: the players can only pass or end
     * their opportunity once it has ended (EndDuel).
     *
     * @throws DuelInProgressException
     */
    public function assertNoDuel(Game $game): void
    {
        if ($this->gameStateManager->getState($game)->duel !== null) {
            throw new DuelInProgressException();
        }
    }

    /**
     * The active player has taken an action: they resolve it, then end it (EndResolution), which ends their
     * opportunity to act. Taking other actions before ending it is allowed.
     */
    public function takeAction(Player $player): void
    {
        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setActed(true),
        );
    }

    /**
     * The player has initiated an interrupt or a reaction, whether or not they have the opportunity to act: they
     * resolve it, then end it (EndResolution).
     */
    public function startInterruptOrReaction(Player $player): void
    {
        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setResolvingInterruptsAndReactions($state->getResolvingInterruptsAndReactions() + 1),
        );
    }

    /**
     * A player cannot pass while they resolve what they have played.
     *
     * @throws ResolutionInProgressException
     */
    public function assertNotResolving(Player $player): void
    {
        if ($this->playerStateManager->getState($player)->isResolving()) {
            throw new ResolutionInProgressException();
        }
    }

    /**
     * An interrupt or a reaction can be ended anytime, during a duel too. An action is ended by the active player once
     * no duel is being resolved.
     *
     * @throws NothingToResolveException|DuelInProgressException|NotActivePlayerException
     */
    public function assertCanEndResolution(Game $game, Player $player): void
    {
        $state = $this->playerStateManager->getState($player);
        if ($state->getResolvingInterruptsAndReactions() > 0) {
            return;
        }

        if (! $state->hasActed()) {
            throw new NothingToResolveException();
        }

        $this->assertNoDuel($game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    /**
     * Ends the last interrupt or reaction the player has initiated, otherwise their action, which ends their
     * opportunity to act.
     */
    public function endResolution(Game $game, Player $player): void
    {
        if ($this->playerStateManager->getState($player)->getResolvingInterruptsAndReactions() > 0) {
            $this->playerStateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->setResolvingInterruptsAndReactions($state->getResolvingInterruptsAndReactions() - 1),
            );

            return;
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setActed(false),
        );
        $this->passToNextPlayer($game, $player);
    }

    /**
     * The player has used their opportunity to act: the next player who has not passed gets it. When every other
     * player has passed, the player keeps it. Players acting simultaneously keep their opportunity. In action windows,
     * the players who had passed get opportunities to act again.
     */
    public function passToNextPlayer(Game $game, Player $player): void
    {
        if ($this->gameStateManager->getState($game)->countActivePlayers() > 1) {
            return;
        }

        if ($this->getPassingRule($game) === PassingRule::Consecutive) {
            foreach ($game->getPlayers() as $other) {
                $this->playerStateManager->withState(
                    $other,
                    fn (PlayerState $state): PlayerState => $state->setPassed(false),
                );
            }
        }

        $this->activateNextPlayer($game, $player);
    }

    /**
     * The player who has passed is no longer active. When no other player is active, the opportunity goes to the next
     * player who has not passed.
     */
    public function pass(Game $game, Player $player): void
    {
        $this->gameStateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->deactivatePlayer($player),
        );
        if ($this->gameStateManager->getState($game)->countActivePlayers() === 0) {
            $this->activateNextPlayer($game, $player);
        }
    }

    private function activateNextPlayer(Game $game, Player $player): void
    {
        $nextPlayer = $this->getNextPlayerToAct($game, $player);
        if ($nextPlayer instanceof Player) {
            $this->gameStateManager->withState(
                $game,
                fn (GameState $state): GameState => $state->setActivePlayer($nextPlayer),
            );
        }
    }

    /**
     * Players who have passed relinquish their opportunities to act: the next player in player order who has not passed,
     * or null when every other player has passed.
     */
    public function getNextPlayerToAct(Game $game, Player $player): ?Player
    {
        foreach ($this->gameStateManager->getState($game)->getPlayerIdsAfter($player) as $playerId) {
            $next = $game->getPlayer($playerId);
            if (! $this->playerStateManager->getState($next)->isPassed()) {
                return $next;
            }
        }

        return null;
    }
}
