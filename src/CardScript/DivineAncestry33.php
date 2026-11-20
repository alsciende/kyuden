<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\PhaseBeganEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Divine Ancestry.
 */
final readonly class DivineAncestry33 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '33-divine-ancestry';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After a phase begins (Earth Role only).
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->isElementOfRole($you, Element::Earth)
            ? [new ReactionOffer($card)]
            : [];
    }
}
