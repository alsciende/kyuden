<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\StackType;
use App\Event\DynastyPhasePassedEvent;
use App\Message\PutCharacterIntoPlay;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Sneaky Shinjo: it reacts from its province. "Play this character" once its player has passed, without an opportunity
 * to act: put into play (PutCharacterIntoPlay), an approximation, PlayCharacter requiring the opportunity to act.
 */
final readonly class SneakyShinjo10 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '10-sneaky-shinjo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getAbilityStacksOutOfPlay(): array
    {
        return [StackType::RegularProvince, StackType::StrongholdProvince];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DynastyPhasePassedEvent::class => 'onDynastyPhasePassed'];
    }

    /**
     * After you pass in the dynasty phase.
     *
     * @return list<ReactionOffer>
     */
    public function onDynastyPhasePassed(DynastyPhasePassedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->player === $this->triggerConditions->getController($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
