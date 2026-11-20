<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ConflictWonEvent;
use App\Message\GrantConflictOpportunity;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Hisu Mori Toride.
 */
final readonly class HisuMoriToride14 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '14-hisu-mori-toride';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [GrantConflictOpportunity::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After you win a [conflict-military] conflict with more participating characters than your opponent.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);
        $opponent = $this->triggerConditions->getOpponent($you);

        return $this->triggerConditions->hasWonConflict($you)
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && count($this->triggerConditions->getParticipants($you)) > count($this->triggerConditions->getParticipants($opponent))
            ? [new ReactionOffer($card)]
            : [];
    }
}
