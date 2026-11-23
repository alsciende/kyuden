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
 * Shiba Yōjimbō.
 */
final readonly class ShibaYojimbo01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-shiba-yojimbo';
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
     * When the effects of a triggered ability that targets a Shugenja you control would initiate – cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiating $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->player !== $this->triggerConditions->getController($card)
            && $this->triggerConditions->controlsCharacter($this->triggerConditions->getController($card), fn (PhysicalCard $character): bool => $this->triggerConditions->hasTrait($character, 'shugenja'))
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
