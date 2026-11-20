<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictPassedEvent;
use App\Model\ReactionOffer;

/**
 * Mediator of Hostilities.
 */
final readonly class MediatorOfHostilities28 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '28-mediator-of-hostilities';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictPassedEvent::class => 'onConflictPassed'];
    }

    /**
     * After a player passes on declaring a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictPassed(ConflictPassedEvent $event, PhysicalCard $card, Game $game): array
    {
        return [new ReactionOffer($card)];
    }
}
