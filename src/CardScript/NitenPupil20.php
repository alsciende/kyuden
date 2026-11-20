<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorDialsRevealedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Niten Pupil.
 */
final readonly class NitenPupil20 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '20-niten-pupil';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorDialsRevealedEvent::class => 'onHonorDialsRevealed'];
    }

    /**
     * After honor dials are revealed for a duel, if this character is involved in the duel.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorDialsRevealed(HonorDialsRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->duel
            && $this->triggerConditions->isInvolvedInDuel($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
