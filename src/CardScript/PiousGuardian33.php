<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Message\FillProvince;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\PhaseEndingTrigger;

/**
 * Pious Guardian.
 */
final readonly class PiousGuardian33 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '33-pious-guardian';
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
     * When the conflict phase ends, if you control 1 or fewer broken provinces.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->phase === Phase::Conflict && count($this->triggerConditions->getBrokenProvinces($this->triggerConditions->getController($card))) <= 1
            ? [new InterruptOffer($card)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }
}
