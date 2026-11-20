<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Message\PutCharacterIntoPlay;
use App\Model\ReactionOffer;

/**
 * Moto Beastmaster.
 */
final readonly class MotoBeastmaster42 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '42-moto-beastmaster';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted'];
    }

    /**
     * After this character is declared as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        return in_array($card, $event->attackers, true)
            ? [new ReactionOffer($card)]
            : [];
    }
}
