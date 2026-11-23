<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\CharacterDishonoringTrigger;

/**
 * Kitsuki Shomon.
 */
final readonly class KitsukiShomon15 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '15-kitsuki-shomon';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CharacterDishonoringTrigger::class => 'onCharacterDishonoring'];
    }

    /**
     * When another character you control would be dishonored: instead, dishonor this character.
     *
     * @return list<InterruptOffer>
     */
    public function onCharacterDishonoring(CharacterDishonoringTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card !== $card && $trigger->controller === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
