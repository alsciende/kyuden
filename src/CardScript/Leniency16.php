<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use App\Message\CancelImminentTrigger;
use App\Message\PutCharacterIntoPlay;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * Leniency.
 */
final readonly class Leniency16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-leniency';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingEffectResolvingTrigger::class => 'onRingEffectResolving'];
    }

    /**
     * When you would resolve a ring effect, choose a character with printed cost 2 or lower in your provinces (face up: the cards face down are hidden from their owner too).
     *
     * @return list<InterruptOffer>
     */
    public function onRingEffectResolving(RingEffectResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $player = $this->triggerConditions->getController($card);
        if ($trigger->player !== $player) {
            return [];
        }

        foreach ($player->getStacks(StackType::RegularProvince) as $stack) {
            foreach ($stack->getCards() as $candidate) {
                if ($candidate instanceof DynastyCard
                    && $candidate->getOrientation() === Orientation::FaceUp
                    && $candidate->getLogicalCard()->getType() === Type::Character
                    && ($candidate->getLogicalCard()->getCost() ?? 0) <= 2) {
                    return [new InterruptOffer($card, would: true)];
                }
            }
        }

        return [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, PutCharacterIntoPlay::class];
    }
}
