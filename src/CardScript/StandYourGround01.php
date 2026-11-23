<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Message\DiscardStatusToken;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\CardLeavingPlayTrigger;

/**
 * Stand Your Ground.
 */
final readonly class StandYourGround01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-stand-your-ground';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, DiscardStatusToken::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlayTrigger::class => 'onCardLeavingPlay'];
    }

    /**
     * When an honored character you control would leave play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlayTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->character && $trigger->controller === $this->triggerConditions->getController($card) && $this->triggerConditions->isHonored($trigger->card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
