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
 * Minami Kaze Regulars.
 */
final readonly class MinamiKazeRegulars14 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '14-minami-kaze-regulars';
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
     * After this character wins a conflict in which you have more participating characters than your opponent.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $opponent = $this->triggerConditions->getOpponent($you);

        return $this->triggerConditions->hasCharacterWonConflict($card)
            && count($this->triggerConditions->getParticipants($you)) > count($this->triggerConditions->getParticipants($opponent))
            ? [new ReactionOffer($card)]
            : [];
    }
}
