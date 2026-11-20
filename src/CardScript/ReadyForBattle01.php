<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardBowedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Ready for Battle.
 */
final readonly class ReadyForBattle01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-ready-for-battle';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardBowedEvent::class => 'onCardBowed'];
    }

    /**
     * After an opponent's card effect or a ring effect bows a character you control: offered for every character bowed by an effect (the engine does not know whose effect it applies).
     *
     * @return list<ReactionOffer>
     */
    public function onCardBowed(CardBowedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->byCardEffect
            && $event->card->isCharacterInPlay()
            && $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
