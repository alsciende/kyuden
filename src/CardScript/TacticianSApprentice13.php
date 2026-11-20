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
 * Tactician's Apprentice.
 */
final readonly class TacticianSApprentice13 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '13-tactician-s-apprentice';
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
     * After honor dials are revealed, if your bid is lower than an opponent's.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorDialsRevealed(HonorDialsRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $opponent = $this->triggerConditions->getOpponent($you);

        return $event->revealedBy($you)
            && $event->revealedBy($opponent)
            && $this->triggerConditions->getHonorBid($you) < $this->triggerConditions->getHonorBid($opponent)
            ? [new ReactionOffer($card)]
            : [];
    }
}
