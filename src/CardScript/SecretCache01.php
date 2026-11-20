<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\ShuffleConflictDeck;
use App\Model\ReactionOffer;

/**
 * Secret Cache.
 */
final readonly class SecretCache01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-secret-cache';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, ShuffleConflictDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * After a conflict is declared against this province.
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
