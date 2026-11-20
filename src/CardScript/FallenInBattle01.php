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
 * Fallen in Battle.
 */
final readonly class FallenInBattle01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-fallen-in-battle';
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
     * After you win a [conflict-military] conflict by 5 or more skill.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->hasWonConflict($you)
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->getWinningMargin($game) >= 5
            ? [new ReactionOffer($card)]
            : [];
    }
}
