<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Exception\Data\DataException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\DeclareConflict;
use App\Repository\CardRepository;
use App\Service\AvailableActionsResolver;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\Trigger\ConflictDeclaringTrigger;
use App\Trigger\ConflictLosingTrigger;
use App\Trigger\GloryCountBeginningTrigger;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * The steps of a conflict that cards interrupt: its declaration (DeclareConflict), its result
 * (`result_determined`, when a player would lose it), the ring effect resolved by the attacking player once they have
 * won it (entering `resolve_ring_effects`: `break_reactions_passed`, `no_province_broken`), and the glory count for
 * the Imperial Favor (`imperial_favor_contest_began`).
 */
final readonly class ConflictTriggerResolver implements ImminentTriggerResolverInterface
{
    /**
     * The steps entering `resolve_ring_effects`.
     */
    private const array RING_EFFECT_STEPS = ['break_reactions_passed', 'no_province_broken'];

    public function __construct(
        private CardRepository $cardRepository,
        private AvailableActionsResolver $availableActionsResolver,
        private ConflictService $conflictService,
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof DeclareConflict) {
            return [];
        }

        try {
            $attacker = $game->getPlayer($message->getPlayerId());
            $this->availableActionsResolver->getHandler(DeclareConflict::class)->assertAvailable($game, $attacker);
            $province = $this->cardRepository->get($message->getProvinceId(), $game);
            $defender = $this->conflictService->assertAttackable($province, $attacker, $message->isIgnoreStrongholdRestriction());
        } catch (DataException|RulesException) {
            // refused by its handler: nothing would occur
            return [];
        }

        if (! $province instanceof ProvinceCard || ! $this->gameStateManager->getState($game)->isRingUnclaimed($message->getRing())) {
            return [];
        }

        return [new ConflictDeclaringTrigger($attacker, $defender, $province, $message->getRing(), $message->getType())];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition === 'imperial_favor_contest_began') {
            return [new GloryCountBeginningTrigger()];
        }

        if ($transition === 'result_determined') {
            $conflict = $this->gameStateManager->getState($game)->currentConflict();
            $winnerId = $conflict->winnerIdFor($conflict->attackerSkill ?? 0, $conflict->defenderSkill ?? 0);
            if ($winnerId === null) {
                return [];
            }

            $loserId = $winnerId === $conflict->attackingPlayerId ? $conflict->defendingPlayerId : $conflict->attackingPlayerId;

            return [new ConflictLosingTrigger($game->getPlayer($loserId), $game->getPlayer($winnerId))];
        }

        if (in_array($transition, self::RING_EFFECT_STEPS, true)) {
            $conflict = $this->gameStateManager->getState($game)->currentConflict();

            return $conflict->attackerWon()
                ? [new RingEffectResolvingTrigger($conflict->ring, $game->getPlayer($conflict->attackingPlayerId))]
                : [];
        }

        return [];
    }
}
