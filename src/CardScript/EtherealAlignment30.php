<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Message\RemoveFromGame;
use App\Message\RestoreProvince;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\PhaseEndingTrigger;

/**
 * Ethereal Alignment.
 */
final readonly class EtherealAlignment30 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '30-ethereal-alignment';
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
     * When the conflict phase ends, choose a broken province that shares an element with a ring in your claimed ring pool.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseEnding(PhaseEndingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        if ($trigger->phase !== Phase::Conflict) {
            return [];
        }

        $player = $this->triggerConditions->getController($card);
        $rings = $this->triggerConditions->getClaimedRings($player);
        foreach ($this->triggerConditions->getBrokenProvinces($player) as $province) {
            foreach ($rings as $ring) {
                if ($this->triggerConditions->hasElement($province, $ring)) {
                    return [new InterruptOffer($card)];
                }
            }
        }

        return [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class, RestoreProvince::class];
    }
}
