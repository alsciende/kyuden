<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\PlaceHonorTokens;
use App\Model\ReactionOffer;

/**
 * Fortified Assembly.
 */
final readonly class FortifiedAssembly22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-fortified-assembly';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceHonorTokens::class];
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
