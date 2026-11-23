<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Clan;
use App\Message\CancelImminentTrigger;
use App\Message\PlaceCardUnder;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\CardLeavingPlay;

/**
 * Back-Alley Hideaway.
 */
final readonly class BackAlleyHideaway04 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '04-back-alley-hideaway';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, PlaceCardUnder::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * When a [clan-scorpion] character you control leaves play (instead of its discard pile).
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->character && $trigger->controller === $this->triggerConditions->getController($card) && $trigger->card->getLogicalCard()->getClan() === Clan::Scorpion
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
