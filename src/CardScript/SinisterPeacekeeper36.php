<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\HonorGainedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Sinister Peacekeeper.
 */
final readonly class SinisterPeacekeeper36 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '36-sinister-peacekeeper';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [HonorGainedEvent::class => 'onHonorGained'];
    }

    /**
     * After an opponent gains 1 or more honor.
     *
     * @return list<ReactionOffer>
     */
    public function onHonorGained(HonorGainedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
