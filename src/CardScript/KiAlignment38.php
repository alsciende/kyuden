<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\DefendersDeclaredEvent;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\RevealCards;
use App\Message\ShuffleConflictDeck;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Ki Alignment.
 */
final readonly class KiAlignment38 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '38-ki-alignment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, RevealCards::class, ShuffleConflictDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            DefendersDeclaredEvent::class => 'onDefendersDeclared',
            ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted',
        ];
    }

    /**
     * After you declare a Monk character as a defender.
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && array_filter($event->defenders, fn (PhysicalCard $defender): bool => $this->triggerConditions->hasTrait($defender, 'monk')) !== []
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After you declare a Monk character as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->attacker === $you
            && array_filter($event->attackers, fn (PhysicalCard $attacker): bool => $this->triggerConditions->hasTrait($attacker, 'monk')) !== []
            ? [new ReactionOffer($card)]
            : [];
    }
}
