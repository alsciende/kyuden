<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiatingTrigger;

/**
 * Voice of Honor.
 */
final readonly class VoiceOfHonor01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-voice-of-honor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [EffectsInitiatingTrigger::class => 'onEffectsInitiating'];
    }

    /**
     * When the effects of an event would initiate, if you control more honored characters than an opponent – cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiatingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->event && $trigger->player !== $this->triggerConditions->getController($card)
            && $this->triggerConditions->countHonoredCharacters($this->triggerConditions->getController($card)) > $this->triggerConditions->countHonoredCharacters($trigger->player)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
