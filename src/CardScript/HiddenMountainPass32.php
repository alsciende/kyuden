<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Message\FillProvince;
use App\Message\TurnProvinceFacedown;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\PhaseEndingTrigger;

/**
 * Hidden Mountain Pass.
 */
final readonly class HiddenMountainPass32 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '32-hidden-mountain-pass';
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
     * When the conflict phase ends, if this holding's province is unbroken.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $province = $this->triggerConditions->getProvinceOf($card);

        return $trigger->phase === Phase::Conflict && $province instanceof ProvinceCard && $province->getCurrentPlace() !== ProvinceCard::STATE_BROKEN
            ? [new InterruptOffer($card)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class, TurnProvinceFacedown::class];
    }
}
