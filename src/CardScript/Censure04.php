<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiating;

/**
 * Censure.
 */
final readonly class Censure04 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '04-censure';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [EffectsInitiating::class => 'onEffectsInitiating'];
    }

    /**
     * When the effects of an event would initiate – cancel those effects. Play only if you have the Imperial Favor.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiating $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->event && $trigger->player !== $this->triggerConditions->getController($card) && $this->triggerConditions->hasImperialFavor($this->triggerConditions->getController($card))
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
