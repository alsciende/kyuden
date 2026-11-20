<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ConflictWonEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Spoils of War.
 */
final readonly class SpoilsOfWar17 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '17-spoils-of-war';
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
     * After you win a [conflict-military] conflict as the attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->hasWonConflict($you)
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isAttackingPlayer($you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
