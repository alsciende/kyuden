<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * 3.2.6: the attacking player, if they won the conflict, may resolve the ring effect with effect messages (TransferHonor,
 * GainHonor, DrawConflictCards, DiscardRandomCardFromHand, HonorCharacter, DishonorCharacter, ReadyCard, BowCard,
 * RemoveFate), then passes. The other players have nothing to do: they are considered to have passed, so the step ends
 * at once when the attacking player did not win.
 */
#[AsEnterListener(workflow: 'game', place: 'resolve_ring_effects')]
readonly class ResolveRingEffectsEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();
        $conflict = $this->stateManager->getState($game)->currentConflict();
        $resolverId = $conflict->attackerWon() ? $conflict->attackingPlayerId : null;

        $this->stateManager->withState($game, function (GameState $state) use ($resolverId): GameState {
            $state->activePlayerIds = $resolverId === null ? [] : [$resolverId];

            return $state;
        });
        foreach ($game->getPlayers() as $player) {
            $this->playerStateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->setPassed($player->getId() !== $resolverId),
            );
        }
    }
}
