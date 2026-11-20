<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\ProvinceRevealedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Seeker of Void.
 */
final readonly class SeekerOfVoid01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-of-void';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceRevealedEvent::class => 'onProvinceRevealed'];
    }

    /**
     * After an [element-void] province you control is revealed.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceRevealed(ProvinceRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $this->triggerConditions->isControlledBy($card, $event->controller)
            && $this->triggerConditions->hasElement($event->province, Element::Void)
            ? [new ReactionOffer($card)]
            : [];
    }
}
