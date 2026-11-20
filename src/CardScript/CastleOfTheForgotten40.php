<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceBrokenEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Castle of the Forgotten.
 */
final readonly class CastleOfTheForgotten40 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '40-castle-of-the-forgotten';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBrokenEvent::class => 'onProvinceBroken'];
    }

    /**
     * After you break a province.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceBroken(ProvinceBrokenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->breaker === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
