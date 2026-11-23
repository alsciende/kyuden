<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\StackType;
use App\Message\CancelImminentTrigger;
use App\Message\PutCharacterIntoPlay;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiatingTrigger;

/**
 * Yogo Kikuyo.
 */
final readonly class YogoKikuyo08 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '08-yogo-kikuyo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getAbilityStacksOutOfPlay(): array
    {
        return [StackType::Hand];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [EffectsInitiatingTrigger::class => 'onEffectsInitiating'];
    }

    /**
     * When the effects of a Spell event your opponent plays during a conflict would initiate, put this character into play from your hand – cancel those effects.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiatingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->event && $trigger->player !== $this->triggerConditions->getController($card)
            && $this->triggerConditions->hasTrait($trigger->card, 'spell') && $this->triggerConditions->isConflictInProgress($game)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, PutCharacterIntoPlay::class];
    }
}
