<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictWonEvent;
use App\Message\BreakProvince;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Matsu Tsuko.
 */
final readonly class MatsuTsuko27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-matsu-tsuko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [BreakProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After this character wins a conflict as the attacker against a non-stronghold province, if you are more honorable than your opponent.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $opponent = $this->triggerConditions->getOpponent($you);

        return $this->triggerConditions->hasCharacterWonConflict($card)
            && $this->triggerConditions->isAttacking($card)
            && ! $this->triggerConditions->isAgainstStrongholdProvince($game)
            && $this->triggerConditions->isMoreHonorable($you, $opponent)
            ? [new ReactionOffer($card)]
            : [];
    }
}
