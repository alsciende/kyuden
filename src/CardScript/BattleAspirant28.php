<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Model\ReactionOffer;

/**
 * Battle Aspirant.
 */
final readonly class BattleAspirant28 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '28-battle-aspirant';
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
     * After this character is declared as an attacker in a [conflict-military] conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        return in_array($card, $event->attackers, true)
            && $event->type === ConflictType::Military
            ? [new ReactionOffer($card)]
            : [];
    }
}
