<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardBowedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Curry Favor.
 */
final readonly class CurryFavor05 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '05-curry-favor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardBowedEvent::class => 'onCardBowed'];
    }

    /**
     * After a character you control bows as a result of conflict resolution from the second conflict you declared this round.
     *
     * @return list<ReactionOffer>
     */
    public function onCardBowed(CardBowedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return ! $event->byCardEffect
            && $this->triggerConditions->isControlledBy($event->card, $you)
            && $this->triggerConditions->isAttackingPlayer($you)
            && $this->triggerConditions->countDeclaredConflicts($you) === 2
            ? [new ReactionOffer($card)]
            : [];
    }
}
