<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiating;

/**
 * Cursecatcher.
 */
final readonly class Cursecatcher31 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '31-cursecatcher';
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
     * When the effects of a province's triggered ability would initiate, if that province has any facedown cards in it – cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiating $trigger, PhysicalCard $card, Game $game): array
    {
        return ! $trigger->event && $trigger->player !== $this->triggerConditions->getController($card) && $trigger->card instanceof ProvinceCard && $this->triggerConditions->hasFacedownCards($trigger->card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
