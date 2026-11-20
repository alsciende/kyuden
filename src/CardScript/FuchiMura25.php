<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\PlaceFateOnRing;
use App\Model\ReactionOffer;

/**
 * Fuchi Mura.
 */
final readonly class FuchiMura25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-fuchi-mura';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceFateOnRing::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * After an attack is declared against this province.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->province === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
