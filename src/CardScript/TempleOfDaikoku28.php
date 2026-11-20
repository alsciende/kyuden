<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Model\ReactionOffer;

/**
 * Temple of Daikoku.
 */
final readonly class TempleOfDaikoku28 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '28-temple-of-daikoku';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * Forced: after an attack is declared against this province.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->province === $card
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
