<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Secluded Temple.
 */
final readonly class SecludedTemple01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-secluded-temple';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After the conflict phase begins, if an opponent controls more characters than you.
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->phase === Phase::Conflict
            && $this->triggerConditions->countCharacters($this->triggerConditions->getOpponent($you)) > $this->triggerConditions->countCharacters($you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
