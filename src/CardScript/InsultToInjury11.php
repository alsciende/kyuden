<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DuelWonEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Insult to Injury.
 */
final readonly class InsultToInjury11 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '11-insult-to-injury';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DuelWonEvent::class => 'onDuelWon'];
    }

    /**
     * After a Duelist character you control wins a duel.
     *
     * @return list<ReactionOffer>
     */
    public function onDuelWon(DuelWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && array_any($this->triggerConditions->getDuelCharacters($you), fn (PhysicalCard $character): bool => $this->triggerConditions->hasTrait($character, 'duelist'))
            ? [new ReactionOffer($card)]
            : [];
    }
}
