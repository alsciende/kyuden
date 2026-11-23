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
 * Ikebana Artisan.
 */
final readonly class IkebanaArtisan12 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '12-ikebana-artisan';
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
     * When you would lose 1 honor for an unopposed conflict: cancel that honor loss.
     *
     * @return list<InterruptOffer>
     */
    public function onHonorLosing(HonorLosingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->unopposed && $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
