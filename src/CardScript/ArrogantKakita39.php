<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DefendersDeclaredEvent;
use App\Message\InitiateDuel;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Arrogant Kakita.
 */
final readonly class ArrogantKakita39 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '39-arrogant-kakita';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DefendersDeclaredEvent::class => 'onDefendersDeclared'];
    }

    /**
     * Forced: after defenders are declared during a conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        return $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
