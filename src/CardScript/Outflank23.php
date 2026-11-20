<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceRevealedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Outflank.
 */
final readonly class Outflank23 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '23-outflank';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceRevealedEvent::class => 'onProvinceRevealed'];
    }

    /**
     * After an opponent's province is revealed during a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceRevealed(ProvinceRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $this->triggerConditions->isConflictInProgress($game)
            && ! $this->triggerConditions->isControlledBy($card, $event->controller)
            ? [new ReactionOffer($card)]
            : [];
    }
}
