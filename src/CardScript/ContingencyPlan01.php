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
 * Contingency Plan.
 */
final readonly class ContingencyPlan01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-contingency-plan';
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
     * After honor dials are revealed (yours among them).
     *
     * @return list<ReactionOffer>
     */
    public function onHonorDialsRevealed(HonorDialsRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->revealedBy($you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
