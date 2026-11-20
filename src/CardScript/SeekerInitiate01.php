<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\RingClaimedEvent;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\ShuffleConflictDeck;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Seeker Initiate.
 */
final readonly class SeekerInitiate01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-initiate';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, ShuffleConflictDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingClaimedEvent::class => 'onRingClaimed'];
    }

    /**
     * After you claim a ring that matches the element of your role.
     *
     * @return list<ReactionOffer>
     */
    public function onRingClaimed(RingClaimedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $this->triggerConditions->isElementOfRole($you, $event->ring)
            ? [new ReactionOffer($card)]
            : [];
    }
}
