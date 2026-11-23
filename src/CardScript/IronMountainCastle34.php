<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\AttachmentBeingPlayed;

/**
 * Iron Mountain Castle.
 */
final readonly class IronMountainCastle34 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '34-iron-mountain-castle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [AttachmentBeingPlayed::class => 'onAttachmentBeingPlayed'];
    }

    /**
     * When you play an attachment on a character you control, bow this stronghold – reduce the cost to play that attachment by 1.
     *
     * @return list<InterruptOffer>
     */
    public function onAttachmentBeingPlayed(AttachmentBeingPlayed $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->player === $this->triggerConditions->getController($card) && $this->triggerConditions->isControlledBy($trigger->target, $trigger->player) && ! $this->triggerConditions->isBowed($card)
            ? [new InterruptOffer($card)]
            : [];
    }
}
