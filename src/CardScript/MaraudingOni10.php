<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\DefendersDeclaredEvent;
use App\Model\ReactionOffer;

/**
 * Marauding Oni.
 */
final readonly class MaraudingOni10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-marauding-oni';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted',
            DefendersDeclaredEvent::class => 'onDefendersDeclared',
        ];
    }

    /**
     * Forced: after this character is declared as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        return in_array($card, $event->attackers, true)
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }

    /**
     * Forced: after this character is declared as a defender.
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        return in_array($card, $event->defenders, true)
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
