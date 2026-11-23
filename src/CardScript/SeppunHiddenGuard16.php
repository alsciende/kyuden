<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Type;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\EffectsInitiating;

/**
 * Seppun Hidden Guard.
 */
final readonly class SeppunHiddenGuard16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-seppun-hidden-guard';
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
     * When the effects of a character ability that targets a unique character you control would initiate, sacrifice this character – cancel those effects. The player who initiated that ability discards 1 random card from their hand.
     *
     * @return list<InterruptOffer>
     */
    public function onEffectsInitiating(EffectsInitiating $trigger, PhysicalCard $card, Game $game): array
    {
        return ! $trigger->event && $trigger->player !== $this->triggerConditions->getController($card) && $trigger->card->getLogicalCard()->getType() === Type::Character
            && $this->triggerConditions->controlsCharacter($this->triggerConditions->getController($card), fn (PhysicalCard $character): bool => $character->getLogicalCard()->getUniqueness() === true)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
