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
 * Finger of Jade.
 */
final readonly class FingerOfJade02 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '02-finger-of-jade';
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
     * When the effects of a card ability that targets attached character would initiate, sacrifice this attachment – cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiatingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->player !== $this->triggerConditions->getController($card) && $card->getAttachedCharacter() instanceof PhysicalCard
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
