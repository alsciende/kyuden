<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiating;

/**
 * Mantis Tenkinja.
 */
final readonly class MantisTenkinja14 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '14-mantis-tenkinja';
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
     * When you play an event, lose 1 honor – reduce the cost of that event by 1.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiating $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->event && $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card)]
            : [];
    }
}
