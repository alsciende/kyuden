<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\PassingRule;
use App\Exception\Rules\CannotPlayFromHandException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
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
     * @throws NotInActionWindowException|CannotPlayFromHandException|NotActivePlayerException
     */
    public function assertCanTakeAction(Game $game, Player $player, bool $playFromHand = false): void
    {
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
