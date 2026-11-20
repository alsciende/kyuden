<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardPlayedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shinjo Ambusher.
 */
final readonly class ShinjoAmbusher16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-shinjo-ambusher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardPlayedEvent::class => 'onCardPlayed'];
    }

    /**
     * After you play this character into a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->card === $card
            && $event->player === $you
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
