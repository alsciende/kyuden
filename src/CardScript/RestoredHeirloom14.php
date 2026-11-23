<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Enum\StackType;
use App\Message\CancelImminentTrigger;
use App\Message\PutAttachmentIntoPlay;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * Restored Heirloom.
 */
final readonly class RestoredHeirloom14 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '14-restored-heirloom';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getAbilityStacksOutOfPlay(): array
    {
        return [StackType::Hand, StackType::ConflictDiscardPile];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingEffectResolvingTrigger::class => 'onRingEffectResolving'];
    }

    /**
     * When you would resolve the water ring effect: instead, put this attachment into play from your hand or conflict discard pile.
     *
     * @return list<InterruptOffer>
     */
    public function onRingEffectResolving(RingEffectResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->ring === Element::Water && $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, PutAttachmentIntoPlay::class];
    }
}
