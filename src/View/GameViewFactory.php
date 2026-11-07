<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use App\Service\AvailableActionsResolver;
use App\Service\SpecialActionsResolver;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\Resolution;
use App\View\Model\CardView;
use App\View\Model\GameView;
use App\View\Model\PlayerView;
use App\View\Model\ResolutionView;
use App\View\Model\SpecialActionView;
use App\View\Model\StackView;

use function Symfony\Component\String\u;

/**
 * Builds the view of a game for one of its players, or for a reader who is not a player (null: a spectator), who only
 * sees what is public and can send nothing. The visibility rules only depend on the state, never on the game
 * place:
 * - the cards follow the rules of Visibility: a hidden stack only shows its number of cards, a hidden card has no code;
 * - the pending honor bid and the chosen stronghold province are shown to their owner only;
 * - everything else is public.
 * The actions available to the viewer come from AvailableActionsResolver, the special actions their cards need from
 * SpecialActionsResolver.
 */
final readonly class GameViewFactory
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private AvailableActionsResolver $availableActionsResolver,
        private SpecialActionsResolver $specialActionsResolver,
        private Visibility $visibility,
    ) {
    }

    public function create(Game $game, ?Player $viewer): GameView
    {
        $state = $this->gameStateManager->getState($game);
        $cards = $this->indexCards($game);

        return new GameView(
            id: $game->getId(),
            version: $game->getVersion(),
            place: $game->getCurrentPlace(),
            viewerId: $viewer?->getId(),
            playerIds: $state->playerIds,
            firstPlayerId: $state->firstPlayerId,
            activePlayerIds: $state->activePlayerIds,
            players: array_values($game->getPlayers()->map(fn (Player $player): PlayerView => $this->createPlayerView($player, $viewer, $cards))->toArray()),
            rings: $state->rings,
            conflict: $state->conflict,
            duel: $state->duel,
            imperialFavorHolderId: $state->imperialFavorHolderId,
            imperialFavorSide: $state->imperialFavorSide,
            gloryCountWinnerId: $state->gloryCountWinnerId,
            winnerId: $state->winnerId,
            victoryCondition: $state->victoryCondition,
            availableActions: $viewer instanceof Player ? $this->availableActionsResolver->resolve($game, $viewer) : [],
            specialActions: array_map(
                fn (string $type, array $cardIds): SpecialActionView => new SpecialActionView($type, $cardIds),
                array_keys($specialActions = $this->specialActionsResolver->resolve($game, $viewer)),
                $specialActions,
            ),
        );
    }

    /**
     * @param array<string, PhysicalCard> $cards the cards of the game by id
     */
    private function createPlayerView(Player $player, ?Player $viewer, array $cards): PlayerView
    {
        $state = $this->playerStateManager->getState($player);
        $isViewer = $player === $viewer;

        $stacks = $player->getStacks()->getValues();
        // stable order: by type, then by id (the stacks of a player have no order)
        usort($stacks, fn (Stack $a, Stack $b): int => [$a->getType()->value, $a->getId()] <=> [$b->getType()->value, $b->getId()]);

        return new PlayerView(
            id: $player->getId(),
            userId: $player->getUserId(),
            name: $player->getUser()->getName(),
            clan: $this->findStronghold($player)?->getLogicalCard()->getClan(),
            honor: $state->getHonor(),
            fate: $state->getFate(),
            honorBid: $state->getHonorBid(),
            pendingHonorBid: $isViewer ? $state->getPendingHonorBid() : null,
            honorBidSelected: $state->getPendingHonorBid() !== null,
            strongholdProvinceId: $isViewer ? $state->getStrongholdProvinceId() : null,
            passed: $state->isPassed(),
            acted: $state->hasActed(),
            resolvingInterruptsAndReactions: $state->countInterruptsAndReactions(),
            resolutions: array_map(fn (Resolution $resolution): ResolutionView => $this->createResolutionView($resolution, $cards[$resolution->cardId] ?? null, $viewer), $state->getResolutions()),
            fateCollected: $state->isFateCollected(),
            conflictOpportunities: $state->getConflictOpportunities(),
            declaredConflictTypes: $state->getDeclaredConflictTypes(),
            declarableConflictTypes: $state->declarableConflictTypes(),
            gloryCount: $state->getGloryCount(),
            stacks: array_map(fn (Stack $stack): StackView => $this->createStackView($stack, $viewer), $stacks),
        );
    }

    /**
     * The cards of the game by id, read from the stacks loaded with the game.
     *
     * @return array<string, PhysicalCard>
     */
    private function indexCards(Game $game): array
    {
        $cards = [];
        foreach ($game->getPlayers() as $player) {
            foreach ($player->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    $cards[$card->getId()] = $card;
                }
            }
        }

        return $cards;
    }

    private function createResolutionView(Resolution $resolution, ?PhysicalCard $card, ?Player $viewer): ResolutionView
    {
        if (! $card instanceof PhysicalCard) {
            return new ResolutionView(null, null, $resolution->timing);
        }

        $stack = $card->getStack();

        return new ResolutionView(
            cardId: $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer) ? $card->getId() : null,
            code: $this->visibility->isCardInStackVisibleTo($card, $stack, $viewer) ? $card->getLogicalCard()->getId() : null,
            timing: $resolution->timing,
        );
    }

    /**
     * The stronghold, read from the stacks loaded with the game (not from all the cards of the player).
     */
    private function findStronghold(Player $player): ?StrongholdCard
    {
        foreach ($player->getStacks(StackType::StrongholdProvince) as $stack) {
            foreach ($stack->getCards() as $card) {
                if ($card instanceof StrongholdCard) {
                    return $card;
                }
            }
        }

        return null;
    }

    private function createStackView(Stack $stack, ?Player $viewer): StackView
    {
        $hidden = ! $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer);

        return new StackView(
            id: $stack->getId(),
            type: u($stack->getType()->name)->snake()->toString(),
            count: $stack->getCards()->count(),
            cards: $hidden ? null : array_map(fn (PhysicalCard $card): CardView => $this->createCardView($card, $viewer), $stack->getOrderedCards()),
            hostId: $stack->getHost()?->getId(),
        );
    }

    private function createCardView(PhysicalCard $card, ?Player $viewer): CardView
    {
        $state = $this->cardStateManager->getState($card);
        $visible = $this->visibility->isCardInStackVisibleTo($card, $card->getStack(), $viewer);

        return new CardView(
            id: $card->getId(),
            code: $visible ? $card->getLogicalCard()->getId() : null,
            side: $card->getLogicalCard()->getSide(),
            orientation: $card->getOrientation(),
            fate: $state->getFate(),
            honorTokens: $state->getHonorTokens(),
            personalHonor: $state->getPersonalHonor(),
            militarySkill: $state->getMilitarySkill(),
            politicalSkill: $state->getPoliticalSkill(),
            controllerId: $state->getControllerId() ?? $card->getPlayer()->getId(),
        );
    }
}
