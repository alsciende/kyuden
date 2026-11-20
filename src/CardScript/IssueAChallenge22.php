<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Issue a Challenge.
 */
final readonly class IssueAChallenge22 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '22-issue-a-challenge';
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
     * After a Bushi character you control attacks alone.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->attacker === $you
            && count($event->attackers) === 1
            && $this->triggerConditions->hasTrait($event->attackers[0], 'bushi')
            ? [new ReactionOffer($card)]
            : [];
    }
}
