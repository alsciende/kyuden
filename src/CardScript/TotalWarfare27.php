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
 * Total Warfare.
 */
final readonly class TotalWarfare27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-total-warfare';
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
     * Forced: after a player loses a conflict at attached province.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $province = $this->triggerConditions->getProvinceOf($card);

        return $province !== null
            && $this->triggerConditions->isAttacked($province)
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
