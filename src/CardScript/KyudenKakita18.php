<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DuelEndedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kyūden Kakita.
 */
final readonly class KyudenKakita18 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '18-kyuden-kakita';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DuelEndedEvent::class => 'onDuelEnded'];
    }

    /**
     * After a duel resolves, if a character you control was involved in it.
     *
     * @return list<ReactionOffer>
     */
    public function onDuelEnded(DuelEndedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return array_any($you->getCharactersInPlay(), $event->wasInvolved(...))
            ? [new ReactionOffer($card)]
            : [];
    }
}
