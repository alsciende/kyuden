<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictWonEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kyūden Ikoma.
 */
final readonly class KyudenIkoma27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-kyuden-ikoma';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After a character you control loses a conflict as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->hasLostConflict($you)
            && $this->triggerConditions->isAttackingPlayer($you)
            && $this->triggerConditions->getParticipants($you) !== []
            ? [new ReactionOffer($card)]
            : [];
    }
}
