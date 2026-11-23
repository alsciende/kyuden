<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Message\FillProvince;
use App\Message\PutCardIntoProvince;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\CardLeavingPlayTrigger;

/**
 * Master of Many Lifetimes.
 */
final readonly class MasterOfManyLifetimes33 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '33-master-of-many-lifetimes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, FillProvince::class, PutCardIntoProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlayTrigger::class => 'onCardLeavingPlay'];
    }

    /**
     * When a character you control would leave play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlayTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->character && $trigger->controller === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
