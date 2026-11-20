<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Nezumi Infiltrator.
 */
final readonly class NezumiInfiltrator12 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '12-nezumi-infiltrator';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardEnteredPlayEvent::class => 'onCardEnteredPlay'];
    }

    /**
     * After this character enters play during a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCardEnteredPlay(CardEnteredPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            && $this->triggerConditions->isConflictInProgress($game)
            ? [new ReactionOffer($card)]
            : [];
    }
}
