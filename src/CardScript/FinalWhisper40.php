<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\PersonalHonorChangedEvent;
use App\Event\StatusTokenPlacedEvent;
use App\Message\PlaceStatusToken;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Final Whisper.
 */
final readonly class FinalWhisper40 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '40-final-whisper';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceStatusToken::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            PersonalHonorChangedEvent::class => 'onPersonalHonorChanged',
            StatusTokenPlacedEvent::class => 'onStatusTokenPlaced',
        ];
    }

    /**
     * After an opponent's character receives a status token: honored or dishonored.
     *
     * @return list<ReactionOffer>
     */
    public function onPersonalHonorChanged(PersonalHonorChangedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->action !== null
            && ! $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After an opponent's character receives a status token: placed on it.
     *
     * @return list<ReactionOffer>
     */
    public function onStatusTokenPlaced(StatusTokenPlacedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->card->isCharacterInPlay()
            && ! $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
