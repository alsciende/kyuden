<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelDuel;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\DuelResolvingTrigger;

/**
 * Stay Your Hand.
 */
final readonly class StayYourHand16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-stay-your-hand';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DuelResolvingTrigger::class => 'onDuelResolving'];
    }

    /**
     * When a duel that targets a character you control would resolve: cancel the duel (CancelDuel).
     *
     * @return list<InterruptOffer>
     */
    public function onDuelResolving(DuelResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        foreach ($this->triggerConditions->getController($card)->getCharactersInPlay() as $character) {
            if (in_array($character->getId(), $trigger->cardIds, true)) {
                return [new InterruptOffer($card, would: true)];
            }
        }

        return [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelDuel::class];
    }
}
