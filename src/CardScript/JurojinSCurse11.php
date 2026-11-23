<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\PhaseEndingTrigger;

/**
 * Jurōjin's Curse.
 */
final readonly class JurojinSCurse11 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '11-jurojin-s-curse';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseEndingTrigger::class => 'onPhaseEnding'];
    }

    /**
     * When the fate phase ends, if attached character is ready (forced): the second fate phase is not played by the engine.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $trigger->phase === Phase::Fate && $character instanceof PhysicalCard && ! $this->triggerConditions->isBowed($character)
            ? [new InterruptOffer($card, forced: true)]
            : [];
    }
}
