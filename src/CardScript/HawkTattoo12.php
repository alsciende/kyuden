<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardPlayedEvent;
use App\Message\GiveActionOpportunity;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Hawk Tattoo.
 */
final readonly class HawkTattoo12 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '12-hawk-tattoo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GiveActionOpportunity::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardPlayedEvent::class => 'onCardPlayed'];
    }

    /**
     * After you play this attachment during a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCardPlayed(CardPlayedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->card === $card
            && $event->player === $you
            && $this->triggerConditions->isConflictInProgress($game)
            ? [new ReactionOffer($card)]
            : [];
    }
}
