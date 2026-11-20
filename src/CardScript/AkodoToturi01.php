<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\RingClaimedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Akodo Toturi.
 */
final readonly class AkodoToturi01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-akodo-toturi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingClaimedEvent::class => 'onRingClaimed'];
    }

    /**
     * After you claim a ring during a [conflict-military] conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onRingClaimed(RingClaimedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
