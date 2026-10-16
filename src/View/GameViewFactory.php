<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Service\AvailableActionsResolver;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\View\Model\CardView;
use App\View\Model\GameView;
use App\View\Model\PlayerView;
use App\View\Model\StackView;

use function Symfony\Component\String\u;

/**
 * Builds the view of a game for one of its players. The visibility rules only depend on the state, never on the game
 * place:
 * - the cards follow the rules of Visibility: a hidden stack only shows its number of cards, a hidden card has no code;
 * - the pending honor bid and the chosen stronghold province are shown to their owner only;
 * - everything else is public.
 * The actions available to the viewer come from AvailableActionsResolver.
 */
final readonly class GameViewFactory
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private AvailableActionsResolver $availableActionsResolver,
        private Visibility $visibility,
    ) {
    }

    public function create(Game $game, Player $viewer): GameView
    {
        $state = $this->gameStateManager->getState($game);

        return new GameView(
            id: $game->getId(),
            version: $game->getVersion(),
            place: $game->getCurrentPlace(),
            viewerId: $viewer->getId(),
            playerIds: $state->playerIds,
            firstPlayerId: $state->firstPlayerId,
            activePlayerIds: $state->activePlayerIds,
            players: array_values($game->getPlayers()->map(fn (Player $player): PlayerView => $this->createPlayerView($player, $viewer))->toArray()),
            rings: $state->rings,
            conflict: $state->conflict,
            duel: $state->duel,
            imperialFavorHolderId: $state->imperialFavorHolderId,
            imperialFavorSide: $state->imperialFavorSide,
            gloryCountWinnerId: $state->gloryCountWinnerId,
            winnerId: $state->winnerId,
            victoryCondition: $state->victoryCondition,
            availableActions: $this->availableActionsResolver->resolve($game, $viewer),
        );
    }

    private function createPlayerView(Player $player, Player $viewer): PlayerView
    {
        $state = $this->playerStateManager->getState($player);
        $isViewer = $player === $viewer;

        $stacks = $player->getStacks()->getValues();
        // stable order: by type, then by id (the stacks of a player have no order)
        usort($stacks, fn (Stack $a, Stack $b): int => [$a->getType()->value, $a->getId()] <=> [$b->getType()->value, $b->getId()]);

        return new PlayerView(
            id: $player->getId(),
            userId: $player->getUserId(),
            honor: $state->getHonor(),
            fate: $state->getFate(),
            honorBid: $state->getHonorBid(),
            pendingHonorBid: $isViewer ? $state->getPendingHonorBid() : null,
            honorBidSelected: $state->getPendingHonorBid() !== null,
            strongholdProvinceId: $isViewer ? $state->getStrongholdProvinceId() : null,
            passed: $state->isPassed(),
            fateCollected: $state->isFateCollected(),
            conflictOpportunities: $state->getConflictOpportunities(),
            declaredConflictTypes: $state->getDeclaredConflictTypes(),
            gloryCount: $state->getGloryCount(),
            stacks: array_map(fn (Stack $stack): StackView => $this->createStackView($stack, $viewer), $stacks),
        );
    }

    private function createStackView(Stack $stack, Player $viewer): StackView
    {
        $hidden = ! $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer);

        return new StackView(
            id: $stack->getId(),
            type: u($stack->getType()->name)->snake()->toString(),
            count: $stack->getCards()->count(),
            cards: $hidden ? null : array_map(fn (PhysicalCard $card): CardView => $this->createCardView($card, $viewer), $stack->getOrderedCards()),
        );
    }

    private function createCardView(PhysicalCard $card, Player $viewer): CardView
    {
        $state = $this->cardStateManager->getState($card);
        $visible = $this->visibility->isCardInStackVisibleTo($card, $card->getStack(), $viewer);

        return new CardView(
            id: $card->getId(),
            code: $visible ? $card->getLogicalCard()->getId() : null,
            orientation: $card->getOrientation(),
            fate: $state->getFate(),
            personalHonor: $state->getPersonalHonor(),
            militarySkill: $state->getMilitarySkill(),
            politicalSkill: $state->getPoliticalSkill(),
        );
    }
}
