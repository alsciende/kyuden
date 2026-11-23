<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\HonorLosingTrigger;

/**
 * Duty.
 */
final readonly class Duty15 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '15-duty';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorLosingTrigger::class => 'onHonorLosing'];
    }

    /**
     * When the effect of a card ability, ring effect, or framework step would cause you to lose your last honor: cancel that effect (not the honor given: a transfer, a duel, the bids).
     *
     * @return list<InterruptOffer>
     */
    public function onHonorLosing(HonorLosingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $player = $this->triggerConditions->getController($card);

        return $trigger->player === $player && $trigger->amount >= $this->triggerConditions->getHonor($player)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
